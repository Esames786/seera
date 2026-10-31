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
        $this->assertSame(__('Received but not invoiced'), ProjectPanelTable::displayCell('orders', 5, 'Received but not invoiced'));
        $this->assertSame('مستلم ولم تتم فوترته', ProjectPanelTable::displayCell('orders', 5, 'Received but not invoiced'));
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
    public function test_restricted_and_posted_cells_translate_in_every_money_column(): void
    {
        app()->setLocale('ar');
        foreach ([['site-expenses', 7], ['equipment', 6], ['equipment', 7], ['warehouses', 6], ['warehouses', 8], ['suppliers', 6], ['orders', 5]] as [$panel, $index]) {
            $this->assertSame(__('Restricted'), ProjectPanelTable::displayCell($panel, $index, 'Restricted'), "$panel[$index]");
            $this->assertNotSame('Restricted', ProjectPanelTable::displayCell($panel, $index, 'Restricted'), "$panel[$index]");
        }
        $this->assertSame('مرحّل', ProjectPanelTable::displayCell('materials', 8, 'posted'));
        $this->assertSame('ملغى', ProjectPanelTable::displayCell('orders', 4, 'cancelled'));
        $this->assertSame('قيد التخطيط', ProjectPanelTable::displayCell('sites', 4, 'planning'));
        $this->assertSame('SITE-A', ProjectPanelTable::displayCell('sites', 0, 'SITE-A'));
        $this->assertSame('Restricted', ProjectPanelTable::displayCell('sites', 1, 'Restricted'), 'a site literally named Restricted is user data');
    }
}
