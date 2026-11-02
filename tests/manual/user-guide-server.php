<?php

// Router for PHP's local development server only; cannot be deployed as an application route.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root = realpath(getenv('SEERA_GUIDE_ROOT') ?: '');
if (! $root || ! preg_match('/^seera-guide-[a-f0-9]{32}$/', basename($root))
    || getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || realpath(getenv('DB_DATABASE') ?: '') !== realpath($root.'/training.sqlite')
    || getenv('ZATCA_PRODUCTION_ENABLED') !== 'false' || ! is_file($root.'/fixture.private.json')) {
    http_response_code(503); exit('Isolated synthetic training fixture required.');
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
if (! in_array($method, ['GET', 'HEAD'], true)
    && ! ($method === 'POST' && in_array($path, ['/login', '/admin/locale'], true))) {
    http_response_code(405); exit('Business writes are disabled on the screenshot server.');
}
$public = realpath(__DIR__.'/../../public');
$file = realpath($public.$path);
if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || realpath(config('database.connections.sqlite.database')) !== realpath($root.'/training.sqlite')
    || config('zatca.production_enabled')) {
    http_response_code(503); exit('Training application configuration mismatch.');
}
if ($path === '/__guide_fixture_attestation') {
    header('Content-Type: application/json');
    echo json_encode(['synthetic' => true, 'id' => basename($root), 'business_writes' => false,
        'production_zatca' => false, 'source' => trim(shell_exec('git rev-parse HEAD'))]);
    return;
}
$app->handleRequest(Illuminate\Http\Request::capture());
