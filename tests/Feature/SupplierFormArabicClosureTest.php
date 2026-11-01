<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierFormArabicClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_supplier_edit_localizes_fields_without_changing_submit_names_or_options(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.master.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('اسم المورد *')
            ->assertSee('المدفوعات والبيانات البنكية')
            ->assertSee('تُستحق الفواتير التي ليس لها تاريخ استحقاق')
            ->assertSee('اسم صاحب الحساب')
            ->assertSee('name="allowed_payment_types"', false)
            ->assertSee('name="payment_term_id"', false)
            ->assertSee('name="linked_account_id"', false)
            ->assertSee('value="active"', false);
    }

    public function test_english_supplier_edit_keeps_original_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::firstOrFail();

        $this->actingAs($admin)->get(route('admin.master.suppliers.edit', $supplier))
            ->assertOk()
            ->assertSee('Supplier Name *')
            ->assertSee('Payment & Banking')
            ->assertSee('Bills without a due date fall due')
            ->assertSee('name="allowed_payment_types"', false);
    }
}
