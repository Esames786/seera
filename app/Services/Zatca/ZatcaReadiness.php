<?php

namespace App\Services\Zatca;

use App\Models\CustomerInvoice;
use App\Models\ZatcaEgsUnit;

/**
 * Readiness checks before any XML is built, with actionable messages. Rules
 * cited are the XML Implementation Standard v1.2 KSA rules and the Data
 * Dictionary (vF 2023-05-19) mandatory flags per document type. Nothing is
 * filled with placeholders: a legally required field that is missing stops
 * the document.
 */
final class ZatcaReadiness
{
    /**
     * @param  array<string, mixed>  $seller
     * @return array{errors: array<int, array{code: string, message: string, rule: string}>, warnings: array<int, array{code: string, message: string, rule: string}>}
     */
    public function seller(array $seller): array
    {
        $errors = [];
        $warnings = [];
        $add = function (string $code, string $rule, ?string $key = null, array $params = []) use (&$errors) {
            $errors[] = ['code' => $code, 'rule' => $rule, 'message' => __('zatca.ready_'.($key ?? $code), $params)];
        };
        if ($seller['name'] === '') {
            $add('seller_name_missing', 'BT-27');
        }
        if (! preg_match('/^3\d{13}3$/', $seller['vat_number'])) {
            $add('seller_vat_invalid', 'BR-KSA-39/40');
        }
        if ($seller['id_value'] === '' || ! in_array($seller['id_scheme'], ['CRN', 'MOM', 'MLS', '700', 'SAG', 'OTH'], true)) {
            $add('seller_id_missing', 'BR-KSA-08');
        }
        foreach (['street' => 'BT-35', 'district' => 'KSA-3', 'city' => 'BT-37'] as $field => $rule) {
            if ($seller[$field] === '') {
                $add('seller_'.$field.'_missing', 'BR-KSA-09');
            }
        }
        if (! preg_match('/^\d{4}$/', $seller['building_number'])) {
            $add('seller_building_invalid', 'BR-KSA-37');
        }
        if (! preg_match('/^\d{5}$/', $seller['postal_code'])) {
            $add('seller_postal_invalid', 'BR-KSA-66');
        }
        if (! preg_match('/^[A-Z]{2}$/', $seller['country_code'])) {
            $add('seller_country_invalid', 'BT-40');
        }
        if ($seller['name_ar'] === '') {
            $warnings[] = ['code' => 'seller_name_ar_missing', 'rule' => 'Arabic human-readable invoice', 'message' => __('zatca.ready_seller_name_ar_missing')];
        }

        return compact('errors', 'warnings');
    }

    /**
     * Buyer rules depend on the document type: a standard (tax) invoice needs the
     * buyer's name, address (street, city, country; BR-KSA-10) and either a VAT
     * number (BT-48) or another identification (BR-KSA-14); a simplified invoice
     * needs none of those (Data Dictionary: buyer fields O / C for 02).
     *
     * @param  array<string, mixed>  $buyer
     * @return array{errors: array<int, array{code: string, message: string, rule: string}>, warnings: array<int, array{code: string, message: string, rule: string}>}
     */
    public function buyer(array $buyer, string $invoiceType): array
    {
        $errors = [];
        $warnings = [];
        $add = function (string $code, string $rule) use (&$errors) {
            $errors[] = ['code' => $code, 'rule' => $rule, 'message' => __('zatca.ready_'.$code)];
        };
        if ($invoiceType === CustomerInvoice::TYPE_SIMPLIFIED) {
            if ($buyer['vat_number'] !== '' && ! preg_match('/^3\d{13}3$/', $buyer['vat_number'])) {
                $warnings[] = ['code' => 'buyer_vat_invalid', 'rule' => 'BR-KSA-44', 'message' => __('zatca.ready_buyer_vat_invalid')];
            }

            return compact('errors', 'warnings');
        }
        if ($buyer['name'] === '') {
            $add('buyer_name_missing', 'BT-44');
        }
        foreach (['street' => 'BT-50', 'city' => 'BT-52'] as $field => $rule) {
            if ($buyer[$field] === '') {
                $add('buyer_'.$field.'_missing', 'BR-KSA-10');
            }
        }
        if (! preg_match('/^[A-Z]{2}$/', $buyer['country_code'])) {
            $add('buyer_country_invalid', 'BT-55');
        }
        if ($buyer['vat_number'] !== '') {
            if (! preg_match('/^3\d{13}3$/', $buyer['vat_number'])) {
                $add('buyer_vat_invalid', 'BR-KSA-44');
            }
        } elseif ($buyer['id_value'] === '' || ! in_array($buyer['id_scheme'], ['TIN', 'CRN', 'MOM', 'MLS', '700', 'SAG', 'NAT', 'GCC', 'IQA', 'PAS', 'OTH'], true)) {
            $add('buyer_id_missing', 'BR-KSA-14');
        }
        if ($buyer['country_code'] === 'SA') {
            if ($buyer['building_number'] !== '' && ! preg_match('/^\d{4}$/', $buyer['building_number'])) {
                $add('buyer_building_invalid', 'BR-KSA-64');
            }
            if ($buyer['postal_code'] !== '' && ! preg_match('/^\d{5}$/', $buyer['postal_code'])) {
                $add('buyer_postal_invalid', 'BR-KSA-67');
            }
            foreach (['building_number', 'district', 'postal_code'] as $field) {
                if ($buyer[$field] === '') {
                    $warnings[] = ['code' => 'buyer_'.$field.'_missing', 'rule' => 'KSA-18 / KSA-4 / BT-53', 'message' => __('zatca.ready_buyer_'.$field.'_missing')];
                }
            }
        }

        return compact('errors', 'warnings');
    }

    /** @return array<int, array{code: string, message: string, rule: string}> */
    public function invoice(CustomerInvoice $invoice): array
    {
        $errors = [];
        if (! in_array($invoice->payment_status, ['unpaid', 'partially_paid', 'paid'], true)) {
            $errors[] = ['code' => 'invoice_not_posted', 'rule' => 'Seera', 'message' => __('zatca.ready_invoice_not_posted')];
        }
        if (! in_array($invoice->invoice_type, CustomerInvoice::TYPES, true)) {
            $errors[] = ['code' => 'invoice_type_invalid', 'rule' => 'BT-3 / KSA-2', 'message' => __('zatca.ready_invoice_type_invalid')];
        }
        if ($invoice->invoice_type === CustomerInvoice::TYPE_STANDARD && ! $invoice->supply_date) {
            $errors[] = ['code' => 'supply_date_missing', 'rule' => 'BR-KSA-15 (KSA-5)', 'message' => __('zatca.ready_supply_date_missing')];
        }
        if ($invoice->lines()->count() === 0) {
            $errors[] = ['code' => 'no_lines', 'rule' => 'BG-25', 'message' => __('zatca.ready_no_lines')];
        }
        if ($invoice->invoice_date && $invoice->invoice_date->isFuture()) {
            $errors[] = ['code' => 'issue_date_future', 'rule' => 'BR-KSA-04', 'message' => __('zatca.ready_issue_date_future')];
        }

        return $errors;
    }

    /** @return array<int, array{code: string, message: string, rule: string}> */
    public function unit(?ZatcaEgsUnit $unit, string $invoiceType): array
    {
        if (! $unit) {
            return [['code' => 'unit_missing', 'rule' => 'Onboarding', 'message' => __('zatca.ready_unit_missing')]];
        }
        $errors = [];
        if (! $unit->hasPrivateKey()) {
            $errors[] = ['code' => 'unit_no_key', 'rule' => 'Onboarding', 'message' => __('zatca.ready_unit_no_key')];
        }
        if (! $unit->hasProductionCredential()) {
            $errors[] = ['code' => 'unit_no_production_csid', 'rule' => 'Onboarding', 'message' => __('zatca.ready_unit_no_production_csid')];
        }
        if ($invoiceType === CustomerInvoice::TYPE_STANDARD && ! $unit->supportsStandard()) {
            $errors[] = ['code' => 'unit_no_standard', 'rule' => 'CSR invoice type (T)', 'message' => __('zatca.ready_unit_no_standard')];
        }
        if ($invoiceType === CustomerInvoice::TYPE_SIMPLIFIED && ! $unit->supportsSimplified()) {
            $errors[] = ['code' => 'unit_no_simplified', 'rule' => 'CSR invoice type (S)', 'message' => __('zatca.ready_unit_no_simplified')];
        }
        if ($unit->status === ZatcaEgsUnit::STATUS_REVOKED) {
            $errors[] = ['code' => 'unit_revoked', 'rule' => 'Onboarding', 'message' => __('zatca.ready_unit_revoked')];
        }

        return $errors;
    }
}
