<?php

/**
 * Builds the shareable HTML edition of the user guide from the Markdown sources.
 *
 *   php docs/user-guide/build-html.php
 *
 * Output (same folder): index.html, SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html,
 * SCREEN-INDEX.html, WORKFLOW-INDEX.html. Self-contained pages (inline CSS,
 * no external assets) so they can be emailed or opened from a shared drive.
 */

require __DIR__.'/../../vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

$dir = __DIR__;
$sources = [
    'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE' => ['title' => 'Current System User Guide', 'toc' => 3],
    'SCREEN-INDEX' => ['title' => 'Screen Index', 'toc' => 2],
    'WORKFLOW-INDEX' => ['title' => 'Workflow Index', 'toc' => 2],
];

/** GitHub-style heading anchors, so the Markdown links keep working in HTML. */
function slug(string $text): string
{
    $text = strtolower(trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)));
    $text = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $text);
    $text = preg_replace('/\s/u', '-', $text);

    return $text;
}

function render(string $markdown, int $tocDepth, array &$toc): string
{
    $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
    $html = (string) $converter->convert($markdown);

    // Anchor ids on headings + table of contents.
    $used = [];
    $html = preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/s', function ($m) use (&$toc, &$used, $tocDepth) {
        $level = (int) $m[1];
        $id = slug($m[2]);
        if (isset($used[$id])) {
            $used[$id]++;
            $id .= '-'.$used[$id];
        } else {
            $used[$id] = 0;
        }
        if ($level >= 2 && $level <= $tocDepth) {
            $toc[] = ['level' => $level, 'id' => $id, 'text' => strip_tags($m[2])];
        }

        return '<h'.$level.' id="'.$id.'">'.$m[2].'</h'.$level.'>';
    }, $html);

    // Site addresses become clickable links (addresses with a {placeholder} stay as plain text).
    $html = preg_replace('/<code>(https:\/\/[^<{]+)<\/code>/', '<a class="url" href="$1" target="_blank" rel="noopener"><code>$1</code></a>', $html);

    // Links between the three documents.
    $html = preg_replace('/href="([A-Z0-9-]+)\.md(#[^"]*)?"/', 'href="$1.html$2"', $html);

    return $html;
}

// Dark reading theme (deliberately single-theme); print falls back to black on white.
$css = <<<'CSS'
:root { --bg:#0f141b; --panel:#161c25; --card:#1b232e; --ink:#e7ecf3; --muted:#9aa7b8; --line:#2a3441; --accent:#3dd2b8; --link:#8cc2ff; --soft:#1f2a36; --code-bg:#0a0e13; --code-ink:#dbe4ef; color-scheme: dark; }
* { box-sizing: border-box; }
body { margin:0; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color:var(--ink); background:var(--bg); line-height:1.55; }
header.top { background:#0a0e13; color:var(--ink); padding:18px 28px; display:flex; flex-wrap:wrap; gap:8px 24px; align-items:baseline; border-block-end:1px solid var(--line); }
header.top .brand { font-size:22px; font-weight:800; letter-spacing:.3px; color:var(--accent); }
header.top .meta { color:var(--muted); font-size:13px; }
header.top nav a { color:var(--link); margin-inline-end:16px; font-size:14px; text-decoration:none; }
header.top nav a:hover { text-decoration:underline; }
.layout { display:grid; grid-template-columns: 300px 1fr; gap:0; min-height:calc(100vh - 64px); }
aside.toc { border-inline-end:1px solid var(--line); background:var(--panel); padding:18px 14px; position:sticky; top:0; height:100vh; overflow:auto; font-size:13px; }
aside.toc h2 { font-size:12px; text-transform:uppercase; letter-spacing:.7px; color:var(--muted); margin:0 0 10px; }
aside.toc a { display:block; color:var(--ink); text-decoration:none; padding:3px 6px; border-radius:4px; }
aside.toc a:hover { background:var(--soft); color:var(--accent); }
aside.toc a.l3 { padding-inline-start:18px; color:var(--muted); font-size:12.5px; }
main { padding:28px 36px 60px; max-width: 1180px; }
main h1 { font-size:30px; margin:0 0 4px; }
main h1 + h1 { font-size:22px; color:var(--accent); font-weight:600; margin-bottom:18px; }
main h2 { font-size:22px; margin:40px 0 12px; padding-block-end:6px; border-block-end:2px solid var(--line); }
main h3 { font-size:17px; margin:28px 0 8px; color:var(--ink); }
main h3::before { content:""; display:inline-block; width:6px; height:18px; background:var(--accent); margin-inline-end:8px; vertical-align:-3px; border-radius:2px; }
main p { margin:8px 0; }
main table { border-collapse:collapse; width:100%; margin:10px 0 16px; font-size:13.5px; background:var(--card); }
main th, main td { border:1px solid var(--line); padding:6px 9px; text-align:start; vertical-align:top; }
main th { background:var(--soft); font-weight:700; }
main tr:nth-child(even) td { background:#182029; }
main code { background:var(--soft); color:var(--code-ink); padding:1px 5px; border-radius:4px; font-size:13px; }
main a.url code { color:var(--link); text-decoration:underline; text-underline-offset:2px; }
main a.url:hover code { background:var(--line); }
main pre { background:var(--code-bg); color:var(--code-ink); padding:14px 16px; border-radius:8px; overflow:auto; font-size:13px; line-height:1.45; border:1px solid var(--line); }
main pre code { background:transparent; color:inherit; padding:0; }
main blockquote { border-inline-start:4px solid var(--accent); margin:10px 0; padding:6px 14px; background:var(--soft); }
main hr { border:0; border-top:1px solid var(--line); margin:32px 0; }
main a { color:var(--link); }
main strong { color:#ffffff; }
footer { color:var(--muted); font-size:12px; padding:20px 36px; border-top:1px solid var(--line); background:var(--panel); }
@media (max-width: 900px) { .layout { grid-template-columns:1fr; } aside.toc { position:static; height:auto; max-height:40vh; } main { padding:18px 16px; } }
@media print { body { background:#fff; color:#111; } header.top nav, aside.toc { display:none; } .layout { display:block; } main { max-width:none; padding:0; } main table, main th, main td { background:#fff; color:#111; border-color:#bbb; } main h2 { page-break-before:always; } main h2:first-of-type { page-break-before:auto; } a { color:inherit; text-decoration:none; } }
CSS;

$nav = '<a href="index.html">Home</a><a href="SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html">User Guide</a><a href="SCREEN-INDEX.html">Screen Index</a><a href="WORKFLOW-INDEX.html">Workflow Index</a>';

$page = function (string $title, string $tocHtml, string $body) use ($css, $nav): string {
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark"><title>'.htmlspecialchars($title).' · Seera ERP</title><style>'.$css.'</style></head><body>'
        .'<header class="top"><span class="brand">Seera ERP</span><span class="meta">'.htmlspecialchars($title).' · Version 1.5 · 4 October 2026 · Current implemented system only</span><nav>'.$nav.'</nav></header>'
        .'<div class="layout"><aside class="toc"><h2>Contents</h2>'.$tocHtml.'</aside><main>'.$body.'</main></div>'
        .'<footer>Seera ERP — Current System User Guide, version 1.5, prepared 4 October 2026. Examples use fictional training data.</footer></body></html>';
};

foreach ($sources as $name => $meta) {
    $markdown = file_get_contents($dir.'/'.$name.'.md');
    $toc = [];
    $body = render($markdown, $meta['toc'], $toc);
    $tocHtml = '';
    foreach ($toc as $entry) {
        // Heading text is already HTML-escaped by the converter; decode before escaping so "&amp;" does not double up.
        $tocHtml .= '<a class="l'.$entry['level'].'" href="#'.$entry['id'].'">'.htmlspecialchars(html_entity_decode($entry['text'], ENT_QUOTES | ENT_HTML5)).'</a>';
    }
    file_put_contents($dir.'/'.$name.'.html', $page($meta['title'], $tocHtml, $body));
    echo $name.'.html: '.number_format(strlen($body)).' bytes, '.count($toc)." contents entries\n";
}

$index = <<<'HTML'
<h1>SEERA ERP</h1><h1>Current System User Guide — HTML edition</h1>
<p>Version 1.5 · Prepared 4 October 2026 · Status: current implemented system only. All examples use fictional training data.</p>
<table><thead><tr><th>Document</th><th>What it is for</th></tr></thead><tbody>
<tr><td><a href="SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html">User Guide</a></td><td>21 chapters: sign-in, screen addresses, fields and buttons, Connected Workspace Standard, 19 end-to-end workflows, troubleshooting, glossary, limitations and quick start by role.</td></tr>
<tr><td><a href="SCREEN-INDEX.html">Screen Index</a></td><td>Permanent Screen IDs, web addresses, navigation, permissions and status, including My Approvals and the embedded PR approval panel.</td></tr>
<tr><td><a href="WORKFLOW-INDEX.html">Workflow Index</a></td><td>The 19 documented workflows (WF-001 … WF-019), including Purchase Request, Site Expense and Supplier Bill runtime approval.</td></tr>
</tbody></table>
<p>Status labels used throughout: <strong>AVAILABLE</strong>, <strong>PARTIAL</strong>, <strong>FOUNDATION ONLY</strong>, <strong>NOT YET OPERATIONAL</strong>. Nothing planned is described as available.</p>
HTML;
file_put_contents($dir.'/index.html', $page('Documentation home', '<a href="SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html">User Guide</a><a href="SCREEN-INDEX.html">Screen Index</a><a href="WORKFLOW-INDEX.html">Workflow Index</a>', $index));
echo "index.html written\n";
