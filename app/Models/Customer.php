<?php

namespace App\Models;

use App\Services\Accounting\PostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Customer extends Model
{
    /** Traffic-light customer rating (client change request NR-06). */
    public const RATINGS = ['Green', 'Amber', 'Red'];

    public const PAYMENT_TYPES = ['Cash', 'Bank', 'Both'];

    protected $fillable = [
        'name', 'code', 'type', 'rating', 'vat_number', 'cr_number', 'opening_receivable',
        'credit_limit', 'contact_person', 'phone', 'email', 'linked_account',
        'billing_address', 'status', 'allowed_payment_types',
    ];

    protected function casts(): array
    {
        return [
            'opening_receivable' => 'decimal:2',
            'credit_limit' => 'decimal:2',
        ];
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public static function typeOptions(?string $current = null): Collection
    {
        return collect(['Company', 'Individual'])->merge(LookupValue::options('customer_type', $current))->unique()->values();
    }

    public function allowedPaymentAccountCodes(): array
    {
        return match ($this->allowed_payment_types) {
            'Cash' => [PostingService::CASH],
            'Bank' => [PostingService::BANK],
            default => [PostingService::CASH, PostingService::BANK],
        };
    }

    public function invoices()
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    /** Office and site contact people (NR-08). */
    public function contacts()
    {
        return $this->hasMany(CustomerContact::class)->orderBy('location_type')->orderBy('name');
    }

    /** Shared notes for visiting staff and Accounts (NR-09). */
    public function notes()
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    /**
     * How far past the agreed due date this customer is (NR-06): the oldest
     * unpaid invoice past due, the number of such invoices and the amount still open.
     *
     * @return array{days: int, count: int, amount: float}
     */
    public function overdueSummary(): array
    {
        $open = $this->invoices()
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->get(['due_date', 'balance_amount']);

        if ($open->isEmpty()) {
            return ['days' => 0, 'count' => 0, 'amount' => 0.0];
        }

        $oldest = $open->min(fn (CustomerInvoice $invoice) => $invoice->due_date);

        return [
            'days' => (int) $oldest->startOfDay()->diffInDays(today()),
            'count' => $open->count(),
            'amount' => round((float) $open->sum('balance_amount'), 2),
        ];
    }
}
