<?php

/**
 * Builds a single shareable page (the three documents in one, with a sticky
 * contents rail) for publishing as a hosted page. Usage:
 *
 *   php docs/user-guide/build-artifact.php <output.html>
 *
 * The output has no <html>/<head>/<body> wrapper on purpose: the hosting
 * page supplies it. Open docs/user-guide/index.html for the local edition.
 */

require __DIR__.'/../../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

$out = $argv[1] ?? null;
if (! $out) {
    fwrite(STDERR, "Usage: php build-artifact.php <output.html>\n");
    exit(1);
}

$dir = __DIR__;
$docs = [
    'guide' => ['file' => 'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE', 'label' => 'User Guide', 'toc' => 2],
    'screens' => ['file' => 'SCREEN-INDEX', 'label' => 'Screen Index', 'toc' => 2],
    'workflows' => ['file' => 'WORKFLOW-INDEX', 'label' => 'Workflow Index', 'toc' => 2],
];
$prefixByFile = ['SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE' => 'guide', 'SCREEN-INDEX' => 'screens', 'WORKFLOW-INDEX' => 'workflows'];

function slug(string $text): string
{
    $text = strtolower(trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)));
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);

    return preg_replace('/\s/u', '-', $text);
}

$converter = new GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
$sections = '';
$rail = '';

foreach ($docs as $prefix => $doc) {
    $html = (string) $converter->convert(file_get_contents($dir.'/'.$doc['file'].'.md'));
    $used = [];
    $toc = [];
    $html = preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/s', function ($m) use (&$toc, &$used, $prefix, $doc) {
        $level = (int) $m[1];
        $id = slug($m[2]);
        if (isset($used[$id])) {
            $used[$id]++;
            $id .= '-'.$used[$id];
        } else {
            $used[$id] = 0;
        }
        if ($level >= 2 && $level <= $doc['toc']) {
            $toc[] = [$level, $prefix.'-'.$id, strip_tags($m[2])];
        }

        return '<h'.$level.' id="'.$prefix.'-'.$id.'">'.$m[2].'</h'.$level.'>';
    }, $html);
    // Site addresses become clickable links (addresses with a {placeholder} stay as plain text).
    $html = preg_replace('/<code>(https:\/\/[^<{]+)<\/code>/', '<a class="url" href="$1" target="_blank" rel="noopener"><code>$1</code></a>', $html);

    // Same-document anchors and cross-document links become in-page anchors.
    $html = preg_replace_callback('/href="(?:([A-Z0-9-]+)\.md)?#([^"]+)"/', function ($m) use ($prefix, $prefixByFile) {
        $target = $m[1] !== '' ? ($prefixByFile[$m[1]] ?? $prefix) : $prefix;

        return 'href="#'.$target.'-'.$m[2].'"';
    }, $html);
    $html = preg_replace_callback('/href="([A-Z0-9-]+)\.md"/', fn ($m) => 'href="#'.($prefixByFile[$m[1]] ?? 'guide').'-top"', $html);

    $sections .= '<section class="doc" id="'.$prefix.'-top"><div class="doc-label">'.$doc['label'].'</div>'.$html.'</section>';
    $rail .= '<div class="rail-group"><a class="rail-doc" href="#'.$prefix.'-top">'.$doc['label'].'</a>';
    foreach ($toc as [$level, $id, $text]) {
        $rail .= '<a class="rail-item" href="#'.$id.'">'.htmlspecialchars(html_entity_decode($text, ENT_QUOTES | ENT_HTML5)).'</a>';
    }
    $rail .= '</div>';
}

$page = <<<'HTML'
<title>Seera ERP User Guide</title>
<meta name="description" content="Current-system user guide for Seera ERP: every screen, the Connected Workspace Standard, end-to-end workflows, screen and workflow indexes.">
<style>
:root {
  --bg:#f7f8f6; --surface:#ffffff; --ink:#1d2430; --muted:#5f6b7a; --line:#dfe4ea; --accent:#0f6b5f; --accent-ink:#ffffff; --link:#1b4fbf; --soft:#eaf3f1; --code-bg:#1b2230; --code-ink:#e6ebf2; --band:#0f172a; --band-ink:#f1f5f9; --band-muted:#b8c2d0;
}
@media (prefers-color-scheme: dark) { :root:not([data-theme="light"]) {
  --bg:#12161c; --surface:#1a2029; --ink:#e6ebf2; --muted:#9aa6b5; --line:#2c3541; --accent:#3fb9a7; --accent-ink:#0b1210; --link:#8fb3ff; --soft:#1d2b29; --code-bg:#0b0f14; --code-ink:#dbe3ee; --band:#0b0f14; --band-ink:#e6ebf2; --band-muted:#8f9bab; color-scheme: dark;
} }
:root[data-theme="dark"] {
  --bg:#12161c; --surface:#1a2029; --ink:#e6ebf2; --muted:#9aa6b5; --line:#2c3541; --accent:#3fb9a7; --accent-ink:#0b1210; --link:#8fb3ff; --soft:#1d2b29; --code-bg:#0b0f14; --code-ink:#dbe3ee; --band:#0b0f14; --band-ink:#e6ebf2; --band-muted:#8f9bab; color-scheme: dark;
}
* { box-sizing: border-box; }
body { background: var(--bg); color: var(--ink); font-family: "Segoe UI", Tahoma, Arial, sans-serif; font-size: 15px; line-height: 1.55; }
.band { background: var(--band); color: var(--band-ink); padding: 18px 20px; display: flex; flex-wrap: wrap; gap: 6px 22px; align-items: baseline; }
.band .brand { font-size: 22px; font-weight: 800; letter-spacing: .3px; }
.band .meta { color: var(--band-muted); font-size: 13px; }
.band nav a { color: #9ec5ff; text-decoration: none; margin-inline-end: 14px; font-size: 14px; }
.band nav a:hover { text-decoration: underline; }
.wrap { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 0; padding: 0 16px 48px; }
.rail { position: sticky; top: env(safe-area-inset-top, 0px); align-self: start; max-height: 100vh; overflow: auto; padding: 16px 10px 16px 0; border-inline-end: 1px solid var(--line); font-size: 13px; }
.rail-group { margin-block-end: 14px; }
.rail-doc { display: block; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; font-size: 11.5px; color: var(--muted); padding: 4px 8px; text-decoration: none; }
.rail-item { display: block; color: var(--ink); text-decoration: none; padding: 3px 8px; border-radius: 5px; }
.rail-item:hover { background: var(--soft); color: var(--accent); }
.rail a:focus-visible, main a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
main { padding: 20px 0 0 28px; max-width: 1100px; min-width: 0; }
.doc { margin-block-end: 56px; }
.doc-label { display: inline-block; background: var(--accent); color: var(--accent-ink); font-weight: 800; letter-spacing: .6px; text-transform: uppercase; font-size: 11.5px; padding: 4px 10px; border-radius: 999px; margin-block-end: 10px; }
main h1 { font-size: 30px; margin: 0 0 4px; text-wrap: balance; }
main h1 + h1 { font-size: 21px; color: var(--accent); font-weight: 600; margin-block-end: 16px; }
main h2 { font-size: 22px; margin: 38px 0 12px; padding-block-end: 6px; border-block-end: 2px solid var(--line); text-wrap: balance; }
main h3 { font-size: 17px; margin: 26px 0 8px; text-wrap: balance; }
main h3::before { content: ""; display: inline-block; width: 6px; height: 17px; background: var(--accent); margin-inline-end: 8px; vertical-align: -3px; border-radius: 2px; }
main p { margin: 8px 0; max-width: 78ch; }
main ul, main ol { max-width: 78ch; }
main a { color: var(--link); }
.table-wrap, main table { max-width: 100%; }
main table { display: block; overflow-x: auto; border-collapse: collapse; margin: 10px 0 16px; font-size: 13.5px; background: var(--surface); font-variant-numeric: tabular-nums; }
main th, main td { border: 1px solid var(--line); padding: 6px 9px; text-align: start; vertical-align: top; }
main th { background: var(--soft); font-weight: 700; }
main code { background: var(--soft); padding: 1px 5px; border-radius: 4px; font-size: 13px; }
main a.url code { color: var(--link); text-decoration: underline; text-underline-offset: 2px; }
main pre { background: var(--code-bg); color: var(--code-ink); padding: 14px 16px; border-radius: 8px; overflow-x: auto; font-size: 13px; line-height: 1.45; max-width: 100%; }
main pre code { background: transparent; color: inherit; padding: 0; }
main hr { border: 0; border-top: 1px solid var(--line); margin: 30px 0; }
@media (max-width: 900px) { .wrap { grid-template-columns: 1fr; } .rail { position: static; max-height: none; border-inline-end: 0; border-block-end: 1px solid var(--line); padding: 12px 0; } main { padding-inline-start: 0; } }
@media (prefers-reduced-motion: no-preference) { html { scroll-behavior: smooth; } }
</style>
HTML;

$band = '<div class="band"><span class="brand">Seera ERP</span><span class="meta">Current System User Guide · Version 1.0 · 27 September 2026 · Current implemented system only · Examples use fictional training data</span><nav><a href="#guide-top">User Guide</a><a href="#screens-top">Screen Index</a><a href="#workflows-top">Workflow Index</a></nav></div>';

file_put_contents($out, $page.$band.'<div class="wrap"><aside class="rail">'.$rail.'</aside><main>'.$sections.'</main></div>');
echo 'written '.number_format(filesize($out))." bytes\n";
