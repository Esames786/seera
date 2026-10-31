<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterWorkspaceUxClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_supplier_view_and_edit_keep_connected_workspace_navigation(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $supplier = Supplier::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تفاصيل المورد')
            ->assertSee('بيانات المورد')
            ->assertSee('العودة إلى الموردين')
            ->assertDontSee('name="_save_action"', false);

        $this->actingAs($admin)->get(route('admin.master.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('مساحة عمل المورد')
            ->assertSee('data-workspace-nav', false)
            ->assertSee('name="_save_action"', false);
    }

    public function test_arabic_customer_and_employee_readonly_headings_keep_record_identity(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $customer = Customer::firstOrFail();
        $employee = Employee::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.customers.show', $customer))
            ->assertOk()
            ->assertSee('الإعدادات الأساسية / العملاء / تفاصيل العميل')
            ->assertSee($customer->code)
            ->assertDontSee('name="_save_action"', false);

        $this->actingAs($admin)->get(route('admin.hr.employees.show', $employee))
            ->assertOk()
            ->assertSee('الموارد البشرية والرواتب / الموظفون / تفاصيل الموظف')
            ->assertSee($employee->employee_code);
    }

    public function test_english_supplier_view_stays_readonly_and_keeps_original_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee('Supplier Details')
            ->assertSee('Supplier Information')
            ->assertSee('Back to Suppliers')
            ->assertDontSee('name="_save_action"', false);
    }

    public function test_supplier_panel_actions_tabs_and_bill_count_are_translated_without_changing_amounts(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $amounts = fn (string $html) => preg_match_all('/SAR [0-9,]+\.[0-9]{2}/u', $html, $m) ? $m[0] : [];

        $english = $this->actingAs($admin)->get(route('admin.master.suppliers.show', $supplier))->assertOk()
            ->assertSee('Supplier Bills')->assertSee('Payments & Balance')->assertDontSee('bill(s)')
            ->assertSeeInOrder(['Outstanding payable', 'open bill']);
        $admin->update(['language' => 'Arabic']);
        $arabic = $this->actingAs($admin->fresh())->get(route('admin.master.suppliers.show', $supplier))->assertOk()
            ->assertSee('فواتير الموردين')->assertSee('المدفوعات والرصيد')->assertSee('عدد الفواتير المفتوحة:')
            ->assertDontSee('open bill')->assertDontSee('>View project<', false)->assertDontSee('>View PO<', false)->assertDontSee('>Record payment<', false)->assertDontSee('>Edit draft<', false);
        $this->assertNotEmpty($amounts($english->getContent()));
        $this->assertSame($amounts($english->getContent()), $amounts($arabic->getContent()));

        $this->actingAs($admin->fresh())->getJson(route('admin.master.suppliers.workspace.panel', [$supplier, 'bills']))->assertOk()
            ->assertDontSee('>View<', false)->assertDontSee('>Edit draft<', false)->assertDontSee('<th>Bill</th>', false);

        $employee = Employee::firstOrFail();
        $this->actingAs($admin->fresh())->get(route('admin.hr.employees.show', $employee))->assertOk()
            ->assertDontSee('>Edit</a>', false)->assertDontSee('New Structure From Profile');
    }
}
