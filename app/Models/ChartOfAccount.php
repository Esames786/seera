<?php

namespace App\Models;

use App\Services\Accounting\PostingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    public const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    /** Account types whose balance grows on the debit side. */
    public const DEBIT_TYPES = ['asset', 'expense'];

    protected $fillable = [
        'account_code', 'account_name', 'account_type', 'parent_id',
        'opening_balance', 'normal_balance', 'vat_applicable',
        'cost_center_required', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'vat_applicable' => 'boolean',
            'cost_center_required' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->account_code.' - '.$this->account_name;
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('account_code');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Net movement from posted journals only, signed toward the account's normal balance.
     */
    public function postedBalance(): float
    {
        $totals = JournalEntryLine::query()
            ->where('chart_of_account_id', $this->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))
            ->selectRaw('COALESCE(SUM(debit), 0) as debit, COALESCE(SUM(credit), 0) as credit')
            ->first();

        $movement = (float) $totals->debit - (float) $totals->credit;

        return $this->normal_balance === 'credit'
            ? (float) $this->opening_balance - $movement
            : (float) $this->opening_balance + $movement;
    }

    /**
     * This account plus everything filed beneath it. Used for control accounts
     * such as Accounts Payable once suppliers can point at sub-accounts.
     */
    public function groupBalance(): float
    {
        $balance = $this->postedBalance();

        foreach ($this->children as $child) {
            $balance += $child->groupBalance();
        }

        return round($balance, 2);
    }

    /** @return array<int, int> */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** The Accounts Payable control account (2100), if the chart has one. */
    public static function payableControl(): ?self
    {
        return static::where('account_code', PostingService::PAYABLE)->first();
    }

    /**
     * Accounts a supplier may be linked to: the payables control account and
     * every active account beneath it, so the control total still covers all
     * supplier balances. Without a control account any active liability qualifies.
     *
     * @return Collection<int, self>
     */
    public static function payableChoices(): Collection
    {
        $control = static::payableControl();

        if (! $control) {
            return static::where('account_type', 'liability')->where('status', 'active')->orderBy('account_code')->get();
        }

        return static::whereIn('id', [$control->id, ...$control->descendantIds()])
            ->where('status', 'active')
            ->orderBy('account_code')
            ->get();
    }

    /**
     * Legacy suppliers stored the payable account as text. Match it to an
     * account by label, code or name; anything unrecognised falls back to the
     * control account, which is where those suppliers have always posted.
     */
    public static function resolvePayableByText(?string $text): ?self
    {
        $text = trim((string) $text);

        if ($text !== '') {
            // Labels are stored as "2100 - Accounts Payable"; the code part is enough.
            $code = str_contains($text, ' - ') ? trim(explode(' - ', $text, 2)[0]) : $text;

            $match = static::query()
                ->where('account_code', $code)
                ->orWhere('account_name', $text)
                ->first();

            if ($match) {
                return $match;
            }
        }

        return static::payableControl();
    }

    /**
     * Next free numeric code directly under a parent (2100 -> 2101, 2102, ...).
     */
    public static function nextChildCode(self $parent): string
    {
        $codes = static::where('parent_id', $parent->id)->pluck('account_code')
            ->filter(fn ($code) => ctype_digit((string) $code))
            ->map(fn ($code) => (int) $code);

        $candidate = $codes->isNotEmpty() ? $codes->max() + 1 : (int) $parent->account_code + 1;

        while (static::where('account_code', (string) $candidate)->exists()) {
            $candidate++;
        }

        return (string) $candidate;
    }
}
