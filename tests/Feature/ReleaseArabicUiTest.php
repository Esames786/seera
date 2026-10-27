<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseArabicUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $this->actingAs($admin);
    }

    public function test_arabic_dashboard_and_workspaces_translate_labels_without_changing_business_values(): void
    {
        $employee = Employee::create(['employee_code' => 'AR-CLOSURE', 'first_name' => 'Original Employee', 'status' => 'active']);
        $customer = Customer::firstOrFail();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('dir="rtl"', false)
            ->assertSee('لوحة التحكم')->assertSee('إجمالي الموظفين')->assertSee('الأدوار النشطة')->assertSee('فتح سجلات النشاط');
        $this->get(route('admin.hr.employees.edit', $employee))->assertOk()
            ->assertSee('مساحة عمل الموظف')->assertSee('الاسم الأول')->assertSee('تاريخ الالتحاق')
            ->assertSee('Original Employee')->assertSee('value="Full Time"', false);
        $this->get(route('admin.master.customers.edit', $customer))->assertOk()
            ->assertSee('مساحة عمل العميل')->assertSee('معلومات العميل')->assertSee('الرقم الضريبي')->assertSee($customer->name);
        $this->assertSame('Original Employee', $employee->fresh()->first_name);
    }

    public function test_arabic_ajax_panel_labels_and_validation_errors_are_localized(): void
    {
        $employee = Employee::create(['employee_code' => 'AR-PANEL', 'first_name' => 'Arabic panel', 'status' => 'active']);
        foreach (['leaves' => 'نوع الإجازة', 'shifts' => 'الوردية', 'salary' => 'الراتب الأساسي', 'account' => 'كلمة المرور'] as $panel => $label) {
            $html = $this->getJson(route('admin.hr.employees.workspace.panel', [$employee, $panel]))->assertOk()->json('html');
            $this->assertStringContainsString($label, $html);
        }
        $response = $this->postJson(route('admin.master.customers.workspace.save', [Customer::firstOrFail(), 'notes']), ['note' => '']);
        $response->assertUnprocessable();
        $this->assertSame('حقل الملاحظة مطلوب.', $response->json('errors.note.0'));
    }

    public function test_english_switch_keeps_original_labels_and_ltr(): void
    {
        $this->post(route('admin.locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('dir="ltr"', false)
            ->assertSee('Total Staff')->assertSee('Active Roles')->assertSee('Open Activity Logs');
    }
}
