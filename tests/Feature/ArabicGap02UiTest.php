<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\EquipmentAsset;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArabicGap02UiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_invoice_create_form_translates_fields_without_altering_tax_or_form_values(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)
            ->get(route('admin.accounting.accounts-receivable.create'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('إضافة فاتورة عميل')
            ->assertSee('سجّل فاتورة عميل.')
            ->assertSee('المحاسبة / الحسابات المدينة / إضافة فاتورة عميل')
            ->assertDontSee('Add Customer Invoice')
            ->assertSee('بيانات الفاتورة')
            ->assertSee('رقم الفاتورة')
            ->assertSee('بنود الفاتورة')
            ->assertSee('حساب الإيرادات')
            ->assertSee('name="lines[0][unit_price]"', false)
            ->assertSee('name="vat_rate"', false)
            ->assertSee('value="15"', false);
    }

    public function test_customer_invoice_edit_page_translates_heading_and_view_details_action(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $customer = Customer::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => $customer->id, 'invoice_date' => now()->toDateString(), 'vat_rate' => 15,
            'lines' => [['description' => 'GAP-02 draft line', 'quantity' => 1, 'unit_price' => 500]],
        ])->assertSessionHasNoErrors();
        $draft = CustomerInvoice::latest('id')->firstOrFail();

        $admin->update(['language' => 'Arabic']);
        $this->actingAs($admin)
            ->get(route('admin.accounting.accounts-receivable.edit', $draft))
            ->assertOk()
            ->assertSee('تعديل الفاتورة: '.$draft->invoice_number)
            ->assertSee('لا يمكن تعديل الفاتورة إلا وهي مسودة')
            ->assertSee('عرض التفاصيل')
            ->assertDontSee('View Details')
            ->assertDontSee('Edit Invoice:')
            ->assertSee('name="vat_rate"', false)
            ->assertSee('name="lines[0][description]"', false);

        $admin->update(['language' => 'English']);
        $this->actingAs($admin)
            ->get(route('admin.accounting.accounts-receivable.edit', $draft))
            ->assertOk()
            ->assertSee('Edit Invoice: '.$draft->invoice_number)
            ->assertSee('Only a draft invoice can be edited')
            ->assertSee('View Details');
    }

    public function test_equipment_help_explains_posting_and_gps_limitations_in_arabic(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $asset = EquipmentAsset::create([
            'asset_code' => 'GAP-AR-001',
            'name' => 'Test Generator',
            'asset_type' => 'equipment',
            'ownership_type' => 'owned',
            'meter_type' => 'hours',
            'status' => 'available',
            'current_meter' => 10,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.equipment.show', $asset))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('لا تصبح كمية الوقود تكلفة للمشروع إلا بعد ترحيل المالية')
            ->assertSee('التتبع المباشر GPS غير متصل بمزود خدمة')
            ->assertSee('غير تشغيلية بعد')
            // Empty-state rows, tracker readiness and operation type labels (document / maintenance) are translated too.
            ->assertSee('هذا الأصل غير مخصص لأي مشروع.')
            ->assertSee('لم يتم تسجيل وقود بعد.')
            ->assertSee('لا يوجد سجل صيانة حتى الآن.')
            ->assertSee('لم يتم رفع مستندات بعد.')
            ->assertSee('لم يتم تسجيل جهاز تتبع.')
            ->assertSee('صيانة وقائية')
            ->assertSee('شهادة الفحص')
            ->assertDontSee('No fuel entries have been recorded.')
            ->assertDontSee('No tracker device recorded.')
            ->assertDontSee('Preventive service')
            ->assertSee('value="preventive"', false)
            ->assertSee('value="inspection"', false)
            ->assertSee('Test Generator');

        // English fallback unchanged.
        $admin->update(['language' => 'English']);
        $this->actingAs($admin)
            ->get(route('admin.equipment.show', $asset))
            ->assertOk()
            ->assertSee('Live GPS tracking is not connected to a telematics provider.')
            ->assertSee('No tracker device recorded.')
            ->assertSee('No fuel entries have been recorded.')
            ->assertSee('Preventive service')
            ->assertSee('NOT YET OPERATIONAL');
    }
}
