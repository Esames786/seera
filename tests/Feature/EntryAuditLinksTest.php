<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntryAuditLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_inventory_reports_duplicate_display_codes_and_real_links_without_mutation(): void
    {
        // The audit must also work on a legacy schema missing today's unique index.
        Schema::table('users', fn ($table) => $table->dropUnique(['employee_id']));
        $one = User::create(['name' => 'First', 'email' => 'first-audit@example.test', 'password' => 'test-password', 'employee_id' => 'EMP-DUP', 'status' => 'active']);
        $two = User::create(['name' => 'Second', 'email' => 'second-audit@example.test', 'password' => 'test-password', 'employee_id' => 'EMP-DUP', 'status' => 'active']);
        $employee = Employee::create(['employee_code' => 'EMP-REAL', 'first_name' => 'Synthetic', 'last_name' => 'Employee', 'employee_classification' => 'sponsorship', 'status' => 'active', 'user_id' => $one->id]);
        $before = [$one->fresh()->getAttributes(), $two->fresh()->getAttributes(), $employee->fresh()->getAttributes()];
        $this->assertSame(0, Artisan::call('seera:entry-audit', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([$one->id, $two->id], $report['duplicate_displayed_employee_codes'][0]['user_ids']);
        $this->assertSame([$employee->id], $report['users'][0]['linked_employee_ids']);
        $this->assertTrue($report['users'][0]['link_review_required']);
        $this->assertTrue($report['read_only']);
        $this->assertSame($before, [$one->fresh()->getAttributes(), $two->fresh()->getAttributes(), $employee->fresh()->getAttributes()]);
    }
}
