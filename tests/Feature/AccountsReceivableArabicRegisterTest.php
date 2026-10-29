<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsReceivableArabicRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_arabic_receivables_register_translates_navigation_and_posting_explanation(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'Arabic']);

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.index'))->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('المحاسبة / الحسابات المدينة')
            ->assertSee('الذمم المدينة المستحقة')
            ->assertSee('فواتير العملاء')
            ->assertSee('اعتماد الفاتورة يُرحّل')
            ->assertSee('name="status"', false)
            ->assertSee('name="zatca"', false);
    }

    public function test_english_receivables_register_retains_existing_labels(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->update(['language' => 'English']);

        $this->actingAs($admin)->get(route('admin.accounting.accounts-receivable.index'))->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Outstanding Receivable')
            ->assertSee('Approving an invoice posts:')
            ->assertSee('name="status"', false);
    }
}
