<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EndOfServiceRecord;
use App\Models\User;
use App\Services\Hr\GratuityCalculator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GratuityTest extends TestCase
{
    use RefreshDatabase;

    private GratuityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GratuityCalculator();
    }

    /** Half a month per year for the first five years. */
    public function test_first_five_years_earn_half_a_month_each(): void
    {
        // 3 years at 10,000 => 0.5 * 10000 * 3
        $this->assertSame(15000.0, $this->calculator->baseGratuity(10000, 3));
        // Exactly 5 years => 0.5 * 10000 * 5
        $this->assertSame(25000.0, $this->calculator->baseGratuity(10000, 5));
    }

    /** A full month per year after the fifth. */
    public function test_years_after_five_earn_a_full_month_each(): void
    {
        // 5 half-years (25,000) + 3 full years (30,000)
        $this->assertSame(55000.0, $this->calculator->baseGratuity(10000, 8));
        // 5 half-years (25,000) + 5 full years (50,000)
        $this->assertSame(75000.0, $this->calculator->baseGratuity(10000, 10));
    }

    public function test_part_years_are_pro_rated(): void
    {
        // 2.5 years => 0.5 * 10000 * 2.5
        $this->assertSame(12500.0, $this->calculator->baseGratuity(10000, 2.5));
    }

    public function test_termination_and_special_cases_pay_the_full_gratuity(): void
    {
        foreach (['termination', 'end_of_contract', 'force_majeure'] as $reason) {
            $this->assertSame(100.0, $this->calculator->entitlementPercentage($reason, 1));
            $this->assertSame(100.0, $this->calculator->entitlementPercentage($reason, 12));
        }
    }

    /** Resignation is scaled by completed years of service. */
    public function test_resignation_is_scaled_by_length_of_service(): void
    {
        $this->assertSame(0.0, $this->calculator->entitlementPercentage('resignation', 1.9));
        $this->assertEqualsWithDelta(33.33, $this->calculator->entitlementPercentage('resignation', 2), 0.01);
        $this->assertEqualsWithDelta(33.33, $this->calculator->entitlementPercentage('resignation', 4.9), 0.01);
        $this->assertEqualsWithDelta(33.33, $this->calculator->entitlementPercentage('resignation', 5), 0.01);
        $this->assertEqualsWithDelta(66.67, $this->calculator->entitlementPercentage('resignation', 9.9), 0.01);
        $this->assertSame(100.0, $this->calculator->entitlementPercentage('resignation', 10));
    }

    public function test_resignation_inside_two_years_pays_nothing(): void
    {
        $result = $this->calculator->calculate(10000, 1.5, 'resignation');

        $this->assertSame(7500.0, $result['base']);
        $this->assertSame(0.0, $result['percentage']);
        $this->assertSame(0.0, $result['gratuity']);
    }

    public function test_service_years_are_derived_from_dates(): void
    {
        $this->assertEqualsWithDelta(1.0, $this->calculator->serviceYears('2024-01-01', '2025-01-01'), 0.02);
        $this->assertSame(0.0, $this->calculator->serviceYears('2025-01-01', '2024-01-01'));
    }
}
