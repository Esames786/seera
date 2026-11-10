<?php

// CLI-only preparation of an existing, newly generated synthetic guide fixture.
require __DIR__.'/../../vendor/autoload.php';
$root = realpath($argv[1] ?? '');
if (PHP_SAPI !== 'cli' || ! $root || ! preg_match('/^seera-guide-[a-f0-9]{32}$/', basename($root)) || ! is_file($root.'/fixture.private.json')) {
    throw new RuntimeException('An isolated synthetic fixture is required.');
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($root.'/training.sqlite') || config('zatca.production_enabled')) {
    throw new RuntimeException('Refusing a nonisolated database.');
}
$fixture = json_decode(file_get_contents($root.'/fixture.private.json'), true, 512, JSON_THROW_ON_ERROR);
if (! ($fixture['synthetic'] ?? false) || ($fixture['id'] ?? '') !== basename($root)) {
    throw new RuntimeException('Synthetic fixture marker mismatch.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]); // Disposable fixture only; no production connection is accepted above.
$employee = App\Models\Employee::withoutGlobalScopes()->where('user_id', $fixture['ids']['user'])->first();
if (! $employee) {
    $employee = App\Models\Employee::findOrFail($fixture['ids']['employee']);
    $employee->update(['user_id' => $fixture['ids']['user']]);
}
if (App\Models\Employee::withoutGlobalScopes()->where('user_id', $fixture['ids']['user'])->count() !== 1) {
    throw new RuntimeException('Synthetic admin must have exactly one employee link.');
}
echo "Synthetic feedback fixture ready; no live data used.\n";
