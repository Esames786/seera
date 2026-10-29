<?php

namespace Tests\Feature;

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
            ->assertSee('بيانات الفاتورة')
            ->assertSee('رقم الفاتورة')
            ->assertSee('بنود الفاتورة')
            ->assertSee('حساب الإيرادات')
            ->assertSee('name="lines[0][unit_price]"', false)
            ->assertSee('name="vat_rate"', false)
            ->assertSee('value="15"', false);
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
            ->assertSee('Test Generator');
    }
}
