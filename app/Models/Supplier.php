<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'code', 'category', 'vat_number', 'cr_number', 'opening_balance',
        'contact_person', 'phone', 'email', 'payment_terms', 'payment_term_id',
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

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function linkedAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'linked_account_id');
    }
}
