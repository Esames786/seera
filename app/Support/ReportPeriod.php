<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Quick date ranges for the accounting reports (client change request NR-21).
 *
 * The preset is resolved once, server-side, so the screen, the CSV export and a
 * manually typed range always agree on the effective dates. Years follow the
 * financial year start configured in `seera.financial_year_start_month`.
 */
final class ReportPeriod
{
    public const PRESETS = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'last_quarter' => 'Last Quarter',
        'year_to_date' => 'Year to Date',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
        'custom' => 'Custom Range',
    ];

    public function __construct(
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly string $preset,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $preset = (string) $request->query('preset', '');
        $today = CarbonImmutable::today();
        $startMonth = max(1, min(12, (int) config('seera.financial_year_start_month', 1)));

        [$from, $to] = match ($preset) {
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today->endOfQuarter()],
            'last_quarter' => [$today->subQuarter()->startOfQuarter(), $today->subQuarter()->endOfQuarter()],
            'year_to_date' => [self::financialYear($today, $startMonth)[0], $today],
            'this_year' => self::financialYear($today, $startMonth),
            'last_year' => self::financialYear($today->subYear(), $startMonth),
            default => [self::date($request->query('from')), self::date($request->query('to'))],
        };

        return new self($from?->startOfDay(), $to?->startOfDay(), array_key_exists($preset, self::PRESETS) ? $preset : 'custom');
    }

    /** Human label shown next to the filters: preset name plus the dates it resolved to. */
    public function label(): string
    {
        if (! $this->from && ! $this->to) {
            return 'All dates';
        }

        $range = ($this->from?->toDateString() ?? 'the beginning').' to '.($this->to?->toDateString() ?? 'today');

        return $this->preset === 'custom' ? $range : self::PRESETS[$this->preset].': '.$range;
    }

    /** File-name friendly form of the range for exports. */
    public function slug(): string
    {
        return ($this->from?->toDateString() ?? 'start').'-to-'.($this->to?->toDateString() ?? 'today');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private static function financialYear(CarbonImmutable $date, int $startMonth): array
    {
        $start = $date->startOfMonth()->setMonth($startMonth);

        if ($start->greaterThan($date)) {
            $start = $start->subYear();
        }

        return [$start, $start->addYear()->subDay()];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn () => CarbonImmutable::parse($value), null, false);
    }
}
