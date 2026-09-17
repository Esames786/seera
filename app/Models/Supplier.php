<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    /** Traffic-light supplier rating (client change request NR-04). */
    public const RATINGS = ['Green', 'Amber', 'Red'];

    /** Payment channels a supplier accepts (NR-05); narrows the payment account choices. */
    public const PAYMENT_TYPES = ['Cash', 'Bank', 'Both'];

    protected $fillable = [
        'name', 'code', 'category', 'city', 'rating', 'vat_number', 'cr_number', 'opening_balance',
        'contact_person', 'phone', 'email', 'bank_name', 'bank_account_name', 'iban',
        'allowed_payment_types', 'payment_terms', 'payment_term_id',
        'linked_account', 'linked_account_id', 'address', 'status',
    ];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }

    /**
     * Keep the legacy text columns and the new links in step: the link wins
     * when set, otherwise recognisable text is resolved to a record so older
     * suppliers (and the seeders) pick up the new fields without a data fix.
     */
    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier) {
            if ($supplier->payment_term_id) {
                $term = PaymentTerm::find($supplier->payment_term_id);
                if ($term) {
                    $supplier->payment_terms = $term->name;
                }
            } elseif (filled($supplier->payment_terms)) {
                $supplier->payment_term_id = PaymentTerm::where('name', $supplier->payment_terms)->value('id');
            }

            if ($supplier->linked_account_id) {
                $account = ChartOfAccount::find($supplier->linked_account_id);
                if ($account) {
                    $supplier->linked_account = $account->label();
                }
            } else {
                $account = ChartOfAccount::resolvePayableByText($supplier->linked_account);
                if ($account) {
                    $supplier->linked_account_id = $account->id;
                    $supplier->linked_account = $account->label();
                }
            }
        });
    }

    /** Account codes this supplier may be paid from, given the channels it accepts. */
    public function allowedPaymentAccountCodes(): array
    {
        return match ($this->allowed_payment_types) {
            'Cash' => [\App\Services\Accounting\PostingService::CASH],
            'Bank' => [\App\Services\Accounting\PostingService::BANK],
            default => [\App\Services\Accounting\PostingService::CASH, \App\Services\Accounting\PostingService::BANK],
        };
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function linkedAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'linked_account_id');
    }

    /** Projects this supplier works for (NR-01). */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'supplier_projects')->withTimestamps();
    }

    public function bills()
    {
        return $this->hasMany(SupplierBill::class);
    }
}
