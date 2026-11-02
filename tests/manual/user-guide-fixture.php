<?php

// CLI-only, fresh disposable SQLite training data. Never uses an existing database.
require __DIR__.'/../../vendor/autoload.php';
$root = realpath($argv[1] ?? '');
if (PHP_SAPI !== 'cli' || ! $root || ! preg_match('/^seera-guide-[a-f0-9]{32}$/', basename($root))) {
    throw new RuntimeException('A uniquely named disposable training directory is required.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($root.'/training.sqlite')
    || config('zatca.production_enabled')) {
    throw new RuntimeException('Refusing a nonisolated database.');
}
if (($argv[2] ?? '') === 'state') {
    $state = [];
    foreach (Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name") as $table) {
        if (str_starts_with($table->name, 'sqlite_')
            || in_array($table->name, ['users', 'activity_logs', 'sessions', 'cache', 'cache_locks'], true)) continue;
        $rows = Illuminate\Support\Facades\DB::select('SELECT * FROM "'.str_replace('"', '""', $table->name).'" ORDER BY rowid');
        $state[$table->name] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows))];
    }
    echo json_encode($state, JSON_PRETTY_PRINT);
    exit;
}
function unlinkedTrainingEmployee(): App\Models\Employee
{
    $found = App\Models\Employee::where('employee_code', 'GUIDE-EMP-01')->first();
    if ($found) return $found;
    $copy = App\Models\Employee::firstOrFail()->replicate();
    $copy->forceFill(['employee_code' => 'GUIDE-EMP-01', 'first_name' => 'Sara', 'last_name' => 'Training',
        'email' => 'sara.training@example.test', 'user_id' => null, 'iqama_number' => null,
        'passport_number' => null, 'iban' => null, 'insurance_number' => null, 'driving_license_number' => null])->save();
    return $copy;
}
if (($argv[2] ?? '') === 'complete-fixture' && is_file($root.'/fixture.private.json')) {
    $facts = json_decode(file_get_contents($root.'/fixture.private.json'), true, flags: JSON_THROW_ON_ERROR);
    $facts['ids']['unlinked_employee'] = unlinkedTrainingEmployee()->id;
    file_put_contents($root.'/fixture.private.json', json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Synthetic employee lookup fixture ready.\n";
    exit;
}
if (Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table'")) {
    throw new RuntimeException('Refusing to overwrite a nonempty training database.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\DatabaseSeeder::class, '--force' => true]);
$admin = App\Models\User::where('email', 'admin@example.com')->firstOrFail();
$password = bin2hex(random_bytes(20));
$admin->forceFill(['name' => 'Training Administrator', 'email' => 'trainer@example.test',
    'username' => 'training.admin', 'password' => Illuminate\Support\Facades\Hash::make($password),
    'must_change_password' => false, 'password_changed_at' => now(), 'language' => 'English'])->save();
foreach (App\Models\User::whereKeyNot($admin->id)->get() as $user) {
    $user->forceFill(['name' => 'Training User '.$user->id, 'email' => 'training-'.$user->id.'@example.test'])->save();
}
$customer = App\Models\Customer::firstOrFail();
$customer->update(['name' => 'Al Noor Training Development', 'code' => 'CUS-TRAIN-01', 'email' => 'customer@example.test', 'contact_person' => 'Training Customer Contact']);
$supplier = App\Models\Supplier::firstOrFail();
$supplier->update(['name' => 'Gulf Training Materials', 'code' => 'SUP-TRAIN-01', 'email' => 'supplier@example.test', 'contact_person' => 'Training Supplier Contact']);
$project = App\Models\Project::firstOrFail();
$project->update(['name' => 'Training Warehouse Renovation', 'code' => 'PRJ-TRAIN-01', 'customer_id' => $customer->id]);
$site = App\Models\Site::where('project_id', $project->id)->firstOrFail();
$site->update(['name' => 'Training Main Site']);
$employee = App\Models\Employee::firstOrFail();
$employee->update(['first_name' => 'Ahmed', 'last_name' => 'Training', 'email' => 'employee@example.test']);
App\Models\CompanyProfile::first()?->update(['name' => 'SEERA Training Company', 'name_ar' => 'شركة سيرا للتدريب', 'email' => 'company@example.test', 'website' => null, 'logo_path' => null]);
$manifest = ['synthetic' => true, 'id' => basename($root), 'source' => trim(shell_exec('git rev-parse HEAD')),
    'user' => $admin->email, 'password' => $password,
    'ids' => ['customer' => $customer->id, 'supplier' => $supplier->id, 'project' => $project->id,
        'site' => $site->id, 'employee' => $employee->id, 'user' => $admin->id,
        'item' => App\Models\Item::firstOrFail()->id, 'warehouse' => App\Models\Warehouse::firstOrFail()->id,
        'unlinked_employee' => unlinkedTrainingEmployee()->id]];
file_put_contents($root.'/fixture.private.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Fresh synthetic training fixture ready. Credentials remain in its private directory.\n";
