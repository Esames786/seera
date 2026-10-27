<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up to the original R22-02 reproducer. Baseline evidence is retained in
 * docs/release-readiness; this now verifies that the same forged request fails.
 */
class RequirementGapReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_non_superadmin_vat_override_is_now_refused(): void
    {
        $this->seed(DatabaseSeeder::class);
        $role = Role::create(['code' => 'UAT-PO-CLERK', 'name' => 'UAT purchasing clerk', 'level' => 4, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->attach(Permission::where('module', 'Purchase Orders')->whereIn('action', ['view', 'create'])->pluck('id'));
        $actor = User::factory()->create(['status' => 'active']);
        $actor->roles()->attach($role, ['is_primary' => true]);
        $this->assertFalse($actor->isSuperAdmin());
        $this->actingAs($actor)->post(route('admin.inventory.purchase-orders.store'), [
            'supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => Warehouse::firstOrFail()->id,
            'po_date' => today()->toDateString(), 'vat_rate' => 5,
            'lines' => [['item_id' => Item::firstOrFail()->id, 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 5]],
        ])->assertSessionHasErrors('vat_rate');
        $this->assertDatabaseMissing('purchase_orders', ['vat_rate' => 5]);
    }
}
