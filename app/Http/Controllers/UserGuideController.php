<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the HTML edition of the current-system user guide (docs/user-guide)
 * to signed-in users. Only the known pages are served; nothing else in the
 * docs folder is reachable.
 */
class UserGuideController extends Controller
{
    public const PAGES = [
        'index',
        'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE',
        'SCREEN-INDEX',
        'WORKFLOW-INDEX',
    ];

    public function show(string $page): Response
    {
        $name = preg_replace('/\.html$/', '', $page);
        abort_unless(in_array($name, self::PAGES, true), 404);

        $path = base_path('docs/user-guide/'.$name.'.html');
        abort_unless(is_file($path), 404, 'The user guide has not been built on this server. Run: php docs/user-guide/build-html.php');

        return response(file_get_contents($path), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
