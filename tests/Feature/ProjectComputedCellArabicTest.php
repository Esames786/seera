<?php

namespace Tests\Feature;

use App\Support\Workspace\ProjectPanelTable;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectComputedCellArabicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_known_system_status_labels_translate_without_modifying_numeric_or_customer_cells(): void
    {
        app()->setLocale('ar');
        $this->assertSame('مدفوع جزئياً', ProjectPanelTable::displayCell('invoices', 8, 'partially_paid'));
        $this->assertSame('تم الاستلام ولم تتم الفوترة', ProjectPanelTable::displayCell('orders', 5, 'Received but not invoiced'));
        $this->assertSame('مقيّد', ProjectPanelTable::displayCell('suppliers', 6, 'Restricted'));
        $this->assertSame('Approved', ProjectPanelTable::displayCell('invoices', 2, 'Approved'));
        $this->assertSame('1,250.00', ProjectPanelTable::displayCell('invoices', 5, '1,250.00'));
        $this->assertSame('Customer Arabic Trading', ProjectPanelTable::displayCell('invoices', 2, 'Customer Arabic Trading'));
        $this->assertSame('Custom status value', ProjectPanelTable::displayCell('invoices', 8, 'Custom status value'));
    }

    public function test_english_system_status_labels_remain_unmodified(): void
    {
        app()->setLocale('en');
        foreach (['partially_paid', 'Fully invoiced', 'Restricted', 'posted'] as $status) {
            $this->assertSame($status, ProjectPanelTable::displayCell('invoices', 8, $status));
        }
    }

    public function test_supplier_bill_approval_labels_have_arabic_translations(): void
    {
        foreach (['Pending Approval', 'Approved — Posting Pending', 'Draft — not submitted', 'Correction — new approval required', 'Legacy — no runtime history'] as $key) {
            $this->assertNotSame($key, __($key, [], 'ar'));
            $this->assertSame($key, __($key, [], 'en'));
        }
    }

    public function test_scoped_arabic_project_panel_still_renders_with_unchanged_routes(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $project = Project::firstOrFail();
        $this->actingAs($admin)->get(route('admin.master.projects.workspace.panel', [$project, 'invoices']))
            ->assertOk()->assertSee('dir="rtl"', false)->assertSee('الإجراءات');
    }
}
