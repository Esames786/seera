<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Item, Permission, PurchaseOrder, Role, Supplier, User, Warehouse};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseVatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function actor(string $name): User
    {
        $role = Role::create(['name' => $name, 'code' => 'VAT-'.strtoupper($name), 'level' => 2, 'access_scope' => 'Company Level', 'status' => 'active']);
        $role->permissions()->attach(Permission::where('module', 'Purchase Orders')->pluck('id'));
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role, ['is_primary' => true]);
        return $user;
    }

    private function payload(): array
    {
        return ['supplier_id' => Supplier::firstOrFail()->id, 'warehouse_id' => Warehouse::firstOrFail()->id,
            'po_date' => today()->toDateString(), 'vat_rate' => 15,
            'lines' => [['item_id' => Item::where('vat_applicable', true)->firstOrFail()->id, 'quantity' => 2, 'unit_price' => 100]]];
    }

    public function test_purchasing_and_finance_cannot_forge_header_or_line_overrides(): void
    {
        $before = PurchaseOrder::count();
        foreach (['Purchasing', 'Finance'] as $role) {
            $actor = $this->actor($role);
            $this->assertFalse($actor->isSuperAdmin());
            foreach (['header', 'line', 'fake-category', 'excess-precision'] as $case) {
                $payload = $this->payload();
                $payload['vat_override_reason'] = 'Forged approval';
                $payload['is_super_admin'] = true;
                if ($case === 'header') {
                    $payload['vat_rate'] = 5;
                } else {
                    $payload['lines'][0]['vat_rate'] = match ($case) { 'line' => 5, 'excess-precision' => '15.0000001', default => 0 };
                    $payload['lines'][0]['vat_applicable'] = false;
                    $payload['lines'][0]['tax_category'] = 'exempt';
                }
                $this->actingAs($actor)->post(route('admin.inventory.purchase-orders.store'), $payload)
                    ->assertSessionHasErrors($case === 'header' ? 'vat_rate' : 'lines.0.vat_rate');
            }
        }
        $this->assertDatabaseCount('purchase_orders', $before);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Overrode purchase VAT']);
    }

    public function test_standard_default_and_configured_non_vat_need_no_override(): void
    {
        $payload = $this->payload();
        unset($payload['vat_rate']);
        $nonVat = Item::whereKeyNot($payload['lines'][0]['item_id'])->firstOrFail();
        $nonVat->update(['vat_applicable' => false]);
        $payload['lines'][] = ['item_id' => $nonVat->id, 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 0];
        $this->actingAs($this->actor('Purchasing'))->post(route('admin.inventory.purchase-orders.store'), $payload)->assertSessionHasNoErrors();
        $order = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertEquals(15, $order->vat_rate);
        $this->assertEquals(30, $order->vat_amount);
        $this->assertEquals(330, $order->total_amount);
        $this->assertEquals([15, 0], $order->lines->pluck('vat_rate')->map(fn ($rate) => (float) $rate)->all());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Overrode purchase VAT']);
    }

    public function test_super_admin_requires_reason_and_override_is_audited(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $payload = $this->payload();
        $payload['vat_rate'] = 5;
        $this->actingAs($admin)->post(route('admin.inventory.purchase-orders.store'), $payload)->assertSessionHasErrors('vat_override_reason');
        $payload['vat_override_reason'] = 'Supplier correction approved by owner';
        $this->post(route('admin.inventory.purchase-orders.store'), $payload)->assertSessionHasNoErrors();
        $order = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertEquals(210, $order->total_amount);
        $log = ActivityLog::where('action', 'Overrode purchase VAT')->sole();
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertStringContainsString($order->po_number, $log->description);
        $this->assertStringContainsString($payload['vat_override_reason'], $log->description);
        $this->assertStringContainsString('"requested":5', $log->description);
    }

    public function test_update_cannot_bypass_policy_and_failed_update_preserves_lines(): void
    {
        $actor = $this->actor('Finance');
        $payload = $this->payload();
        $this->actingAs($actor)->post(route('admin.inventory.purchase-orders.store'), $payload)->assertSessionHasNoErrors();
        $order = PurchaseOrder::latest('id')->firstOrFail();
        $lineId = $order->lines->first()->id;
        $payload['lines'][0]['vat_rate'] = 5;
        $this->put(route('admin.inventory.purchase-orders.update', $order), $payload)->assertSessionHasErrors('lines.0.vat_rate');
        $this->assertEquals(230, $order->fresh()->total_amount);
        $this->assertEquals($lineId, $order->fresh()->lines->first()->id);
    }

    public function test_super_admin_update_is_audited_but_approved_order_is_immutable(): void
    {
        $payload = $this->payload();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())->post(route('admin.inventory.purchase-orders.store'), $payload)->assertSessionHasNoErrors();
        $order = PurchaseOrder::latest('id')->firstOrFail();
        $payload['lines'][0]['vat_rate'] = 5;
        $payload['vat_override_reason'] = 'Approved correction';
        $this->put(route('admin.inventory.purchase-orders.update', $order), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'Overrode purchase VAT']);
        $this->post(route('admin.inventory.purchase-orders.approve', $order))->assertSessionHasNoErrors();
        $payload['lines'][0]['vat_rate'] = 0;
        $this->put(route('admin.inventory.purchase-orders.update', $order), $payload)->assertSessionHasErrors('po');
        $this->assertEquals(210, $order->fresh()->total_amount);
        $this->assertSame('approved', $order->fresh()->status);
    }
}
