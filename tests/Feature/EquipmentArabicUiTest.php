<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentArabicUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_equipment_register_and_add_form_render_arabic_without_changing_field_names(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $this->actingAs($admin);

        $this->get(route('admin.equipment.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('سجل المعدات والمركبات')
            ->assertSee('الأصول ضمن نطاق الصلاحية')
            ->assertSee('التتبع المباشر عبر GPS غير متصل');

        $this->get(route('admin.equipment.create'))->assertOk()
            ->assertSee('رمز الأصل')
            ->assertSee('رقم التسجيل / اللوحة')
            ->assertSee('جاهزية التتبع')
            ->assertSee('name="asset_code"', false)
            ->assertSee('name="tracker_device_id"', false);
    }

    public function test_equipment_register_keeps_existing_english_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);
        $this->actingAs($admin);

        $this->get(route('admin.equipment.index'))->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Equipment Register')
            ->assertSee('Assets in scope');

        $this->get(route('admin.equipment.create'))->assertOk()
            ->assertSee('Asset code')
            ->assertSee('Tracking readiness (no live GPS yet)')
            ->assertSee('name="asset_code"', false);
    }
}
