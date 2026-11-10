<?php

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\SupplierRecoverables;
use App\Services\Notifications\CustomerOverdueReminders;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

// Add fictional examples to the existing attested disposable training fixture only.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$root = realpath($argv[1] ?? '');
if (! $root || ! preg_match('/^seera-guide-[a-f0-9]{32}$/', basename($root))) {
    throw new RuntimeException('Disposable fixture required');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($root.'/training.sqlite') || config('zatca.production_enabled')) {
    throw new RuntimeException('Wrong fixture');
}
$fixture = json_decode(file_get_contents($root.'/fixture.private.json'), true, 512, JSON_THROW_ON_ERROR);
if (! ($fixture['synthetic'] ?? false)) {
    throw new RuntimeException('Missing synthetic marker');
}
$actor = User::findOrFail($fixture['ids']['user']);
$supplier = Supplier::firstOrCreate(['code' => 'CLOSE-S'], ['name' => 'Training Recoverable Supplier', 'status' => 'active', 'allowed_payment_types' => 'Both']);
$asset = ChartOfAccount::firstOrCreate(['account_code' => '1590'], ['account_name' => 'Training supplier advances', 'account_type' => 'asset', 'normal_balance' => 'debit', 'status' => 'active']);
AutomaticPostingRule::updateOrCreate(['source_module' => 'Supplier Advance', 'trigger_event' => 'Payment Recorded'], ['debit_account_id' => $asset->id, 'auto_post' => true, 'approval_required' => false, 'cost_center_rule' => 'None', 'status' => 'active']);
$bank = ChartOfAccount::where('account_code', '1120')->firstOrFail();
$service = app(SupplierRecoverables::class);
$advance = SupplierAdvance::where('supplier_id', $supplier->id)->first();
if (! $advance) {
    $advance = $service->record($supplier, ['payment_date' => today()->toDateString(), 'purpose' => 'salary_on_behalf', 'amount' => 1000,
        'reference' => 'TRAINING-AGREEMENT-01', 'reason' => 'Fictional training recovery agreed with supplier', 'recoverable_confirmed' => 1,
        'payment_account_id' => $bank->id, 'idempotency_key' => (string) Str::uuid()], $actor);
    $service->settle($advance, 'refund', ['movement_date' => today()->toDateString(), 'amount' => 100, 'reference' => 'TRAINING-RETURN-01', 'payment_account_id' => $bank->id, 'idempotency_key' => (string) Str::uuid()], $actor);
}
$customer = Customer::firstOrCreate(['code' => 'CLOSE-C'], ['name' => 'Training Overdue Customer', 'type' => 'Company', 'status' => 'active']);
$invoice = CustomerInvoice::firstOrCreate(['invoice_number' => 'TRAINING-OVERDUE-01'], ['customer_id' => $customer->id,
    'invoice_date' => today()->subDays(20)->toDateString(), 'due_date' => today()->subDay()->toDateString(), 'taxable_amount' => 100, 'vat_rate' => 15,
    'vat_amount' => 15, 'total_amount' => 115, 'balance_amount' => 115, 'payment_status' => 'unpaid']);
if (! $invoice->journal_entry_id) {
    $journal = app(PostingService::class)->postCustomerInvoice($invoice, $actor->id);
    $invoice->update(['journal_entry_id' => $journal->id]);
}
config(['seera.overdue_reminders.enabled' => true]);
app(CustomerOverdueReminders::class)->publish();
file_put_contents($root.'/closure.private.json', json_encode(['supplier' => $supplier->id, 'advance' => $advance->id, 'invoice' => $invoice->id]));
echo "Synthetic closure examples prepared; no live access.\n";
