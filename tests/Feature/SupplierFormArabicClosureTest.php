<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\PaymentTerm;
use App\Models\Project;
use App\Models\VatTransaction;
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

    public function test_create_and_quick_create_help_are_arabic_including_the_account_sentence(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);
        $term = PaymentTerm::create(['name' => 'Custom English agreement', 'days' => 45, 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.master.suppliers.create'))->assertOk()
            ->assertSee('إضافة مورد')->assertSee('رمز الحساب')
            ->assertSee('المشاريع التي يعمل بها المورد')
            ->assertSee('، ليظل إجمالي الدائنين في لوحة المعلومات شاملاً له.')
            ->assertDontSee(', so the payables total on the dashboard still includes it.')
            ->assertSee('Custom English agreement (45 يوماً)')
            ->assertSee('value="'.$term->id.'"', false)
            ->assertSee('name="account_type" value="liability"', false)
            ->assertSee('name="normal_balance" value="credit"', false);
    }

    public function test_create_edit_and_dialog_field_contracts_are_identical_between_locales(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        foreach ([route('admin.master.suppliers.create'), route('admin.master.suppliers.edit', $supplier)] as $url) {
            $contracts = [];
            foreach (['English', 'Arabic'] as $language) {
                $admin->update(['language' => $language]);
                $html = $this->actingAs($admin->fresh())->get($url)->assertOk()->getContent();
                $dom = new \DOMDocument;
                @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
                $xpath = new \DOMXPath($dom);
                $fields = [];
                foreach ($xpath->query('//form[@id="profile" or contains(@class,"quick-create-form")]') as $form) {
                    $controls = [];
                    foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name] | .//button[@name]', $form) as $node) {
                        if ($node->getAttribute('name') === '_token') continue;
                        $options = [];
                        foreach ($xpath->query('.//option', $node) as $option) {
                            $options[] = [$option->getAttribute('value'), $option->hasAttribute('selected')];
                        }
                        $controls[] = [$node->nodeName, $node->getAttribute('name'), $node->getAttribute('type'),
                            $node->getAttribute('value'), $node->hasAttribute('checked'), $node->hasAttribute('required'),
                            $node->nodeName === 'textarea' ? $node->textContent : null, $options];
                    }
                    $fields[] = [$form->getAttribute('action'), $form->getAttribute('method'), $controls];
                }
                $this->assertCount(4, $fields, 'Main supplier form and three quick-create dialogs');
                $contracts[] = $fields;
            }
            $this->assertSame($contracts[0], $contracts[1]);
        }
    }

    public function test_both_locales_save_identical_supplier_data_without_posting_accounting(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $term = PaymentTerm::active()->firstOrFail();
        $account = ChartOfAccount::payableControl();
        $project = Project::firstOrFail();
        $journals = JournalEntry::count();
        $vat = VatTransaction::count();
        $payload = ['name' => 'Green & Bank مورد', 'category' => 'English user category', 'city' => 'Riyadh',
            'rating' => 'Green', 'status' => 'active', 'opening_balance' => '1234.56',
            'payment_term_id' => $term->id, 'linked_account_id' => $account->id, 'allowed_payment_types' => 'Bank',
            'bank_name' => 'Custom Bank', 'bank_account_name' => 'Original Holder', 'iban' => 'SA001234567890',
            'address' => 'Original Address', 'project_ids' => [$project->id], 'project_ids_submitted' => 1];
        $stored = [];
        foreach (['English', 'Arabic'] as $index => $language) {
            $admin->update(['language' => $language]);
            $code = 'QA-LOCALE-'.$index;
            $this->actingAs($admin->fresh())->post(route('admin.master.suppliers.store'), $payload + ['code' => $code, '_save_action' => 'close'])
                ->assertSessionHasNoErrors()->assertRedirect(route('admin.master.suppliers.index'));
            $supplier = Supplier::where('code', $code)->firstOrFail();
            $stored[] = $supplier->only(array_diff(array_keys($payload), ['project_ids', 'project_ids_submitted']));
            $this->assertSame([$project->id], $supplier->projects()->pluck('projects.id')->all());
            $this->actingAs($admin->fresh())->put(route('admin.master.suppliers.update', $supplier),
                array_merge($payload, ['code' => $code, 'bank_account_name' => 'Updated Holder', '_save_action' => 'stay']))
                ->assertSessionHasNoErrors()->assertRedirect(route('admin.master.suppliers.edit', $supplier));
            $this->assertSame('Updated Holder', $supplier->fresh()->bank_account_name);
            $this->assertSame('1234.56', $supplier->fresh()->opening_balance);
            $this->assertSame('Bank', $supplier->fresh()->allowed_payment_types);
        }
        $this->assertSame($stored[0], $stored[1]);
        $this->assertSame($journals, JournalEntry::count());
        $this->assertSame($vat, VatTransaction::count());
    }
    public function test_arabic_supplier_create_page_has_localized_heading_and_unmodified_form_values(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.master.suppliers.create'))
            ->assertOk()
            ->assertSee('إضافة مورد')
            ->assertSee('الإعدادات الأساسية / الموردون / إضافة مورد')
            ->assertSee('name="name"', false)
            ->assertSee('name="allowed_payment_types"', false)
            ->assertSee('value="active"', false);
    }

}
