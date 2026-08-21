<?php

namespace App\Services\Hr;

use Illuminate\Support\Carbon;

/**
 * Saudi end-of-service gratuity.
 *
 * Base award (Labour Law art. 84):
 *   half a month's wage for each of the first five years of service,
 *   a full month's wage for each year after that, pro-rated for part years
 *   and calculated on the final salary.
 *
 * How the service ended then scales that award.
 */
class GratuityCalculator
{
    public const REASONS = ['termination', 'end_of_contract', 'resignation', 'force_majeure', 'marriage', 'childbirth'];

    /** Years of the first band, awarded at half a month each. */
    public const HALF_SALARY_YEARS = 5;

    /**
     * Gratuity before the reason-for-leaving adjustment.
     */
    public function baseGratuity(float $finalSalary, float $serviceYears): float
    {
        if ($finalSalary <= 0 || $serviceYears <= 0) {
            return 0.0;
        }

        $firstBand = min($serviceYears, self::HALF_SALARY_YEARS);
        $laterBand = max($serviceYears - self::HALF_SALARY_YEARS, 0);

        return round(($finalSalary * 0.5 * $firstBand) + ($finalSalary * $laterBand), 2);
    }

    /**
     * Share of the base gratuity the employee actually receives, as a percentage.
     *
     * Employer termination, contract expiry and the art. 87 exceptions
     * (force majeure and similar special cases) all pay in full.
     */
    public function entitlementPercentage(string $reason, float $serviceYears): float
    {
        if ($reason !== 'resignation') {
            return 100.0;
        }

        $fraction = match (true) {
            $serviceYears < 2.0 => 0.0,
            $serviceYears <= 5.0 => 1 / 3,
            $serviceYears < 10.0 => 2 / 3,
            default => 1.0,
        };

        return round($fraction * 100, 2);
    }

    /**
     * @return array{base: float, percentage: float, gratuity: float}
     */
    public function calculate(float $finalSalary, float $serviceYears, string $reason): array
    {
        $base = $this->baseGratuity($finalSalary, $serviceYears);
        $percentage = $this->entitlementPercentage($reason, $serviceYears);

        return [
            'base' => $base,
            'percentage' => $percentage,
            'gratuity' => round($base * $percentage / 100, 2),
        ];
    }

    /**
     * Completed years of service between two dates, to two decimals.
     */
    public function serviceYears($from, $to): float
    {
        if (! $from || ! $to) {
            return 0.0;
        }

        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        return round($start->diffInDays($end) / 365.25, 2);
    }

    /**
     * @return array<string, string>
     */
    public static function reasonLabels(): array
    {
        return [
            'termination' => 'Termination by employer',
            'end_of_contract' => 'Contract completed',
            'resignation' => 'Resignation',
            'force_majeure' => 'Force majeure / special case',
            'marriage' => 'Article 87: resignation after marriage',
            'childbirth' => 'Article 87: resignation after childbirth',
        ];
    }
}
