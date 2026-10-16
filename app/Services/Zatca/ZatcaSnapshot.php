<?php

namespace App\Services\Zatca;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerInvoice;

/**
 * Immutable seller and buyer snapshots. Taken once at issuance (approval) and
 * frozen on the invoice: later edits to the Company Profile or the Customer
 * never change an issued document's XML. Only a record that ZATCA has not
 * accepted may be re-snapshotted, explicitly.
 */
final class ZatcaSnapshot
{
    /** @return array<string, mixed> */
    public function seller(?CompanyProfile $company = null): array
    {
        $company ??= CompanyProfile::first();

        return [
            'name' => trim((string) $company?->name),
            'name_ar' => trim((string) $company?->name_ar),
            'vat_number' => $this->digits($company?->vat_number),
            'id_scheme' => $company?->seller_id_scheme ?: 'CRN',
            'id_value' => $this->alnum($company?->cr_number),
            'street' => trim((string) $company?->street),
            'building_number' => $this->digits($company?->building_number),
            'additional_number' => $this->digits($company?->additional_number),
            'district' => trim((string) $company?->district),
            'city' => trim((string) $company?->city),
            'postal_code' => $this->digits($company?->postal_code),
            'country_code' => strtoupper(trim((string) ($company?->country_code ?: 'SA'))),
        ];
    }

    /** @return array<string, mixed> */
    public function buyer(Customer $customer): array
    {
        return [
            'name' => trim((string) $customer->name),
            'type' => (string) $customer->type,
            'vat_number' => $this->digits($customer->vat_number),
            'id_scheme' => $customer->buyer_id_scheme ?: ($customer->cr_number ? 'CRN' : null),
            'id_value' => $this->alnum($customer->buyer_id_value ?: $customer->cr_number),
            'street' => trim((string) $customer->street),
            'building_number' => $this->digits($customer->building_number),
            'district' => trim((string) $customer->district),
            'city' => trim((string) $customer->city),
            'postal_code' => $this->digits($customer->postal_code),
            'country_code' => strtoupper(trim((string) ($customer->country_code ?: 'SA'))),
        ];
    }

    /** Freeze both snapshots on the invoice unless they already exist (or $force for an unaccepted record). */
    public function freeze(CustomerInvoice $invoice, bool $force = false): CustomerInvoice
    {
        $invoice->loadMissing('customer');
        $changes = [];
        if ($force || empty($invoice->seller_snapshot)) {
            $changes['seller_snapshot'] = $this->seller();
        }
        if ($force || empty($invoice->buyer_snapshot)) {
            $changes['buyer_snapshot'] = $this->buyer($invoice->customer);
        }
        if ($changes !== []) {
            $invoice->forceFill($changes)->save();
        }

        return $invoice;
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }

    private function alnum(?string $value): string
    {
        return preg_replace('/[^A-Za-z0-9]+/', '', (string) $value);
    }
}
