<?php

// Read-only validation of the four authoritative generated HTML pages.
$directory = dirname(__DIR__).'/docs/user-guide/';
$files = ['index.html', 'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html', 'SCREEN-INDEX.html', 'WORKFLOW-INDEX.html'];
$documents = [];
foreach ($files as $file) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    if (! $dom->loadHTML(file_get_contents($directory.$file))) {
        throw new RuntimeException('Invalid HTML: '.$file);
    }
    $documents[$file] = new DOMXPath($dom);
    $seen = [];
    foreach ($documents[$file]->query('//*[@id]') as $node) {
        $id = $node->getAttribute('id');
        if (isset($seen[$id])) {
            throw new RuntimeException('Duplicate anchor '.$file.'#'.$id);
        }
        $seen[$id] = true;
    }
}
$checked = 0;
foreach ($documents as $file => $xpath) {
    foreach ($xpath->query('//a[@href]') as $link) {
        $href = html_entity_decode($link->getAttribute('href'));
        if (preg_match('~^(https?:|mailto:|tel:)~', $href)) {
            continue;
        }
        [$target, $anchor] = array_pad(explode('#', $href, 2), 2, null);
        $target = $target === '' ? $file : rawurldecode($target);
        if (! isset($documents[$target])) {
            throw new RuntimeException('Missing guide link '.$file.' -> '.$href);
        }
        if ($anchor !== null && $anchor !== '') {
            $anchor = rawurldecode($anchor);
            $found = false;
            foreach ($documents[$target]->query('//*[@id]') as $node) {
                if ($node->getAttribute('id') === $anchor) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new RuntimeException('Missing anchor '.$file.' -> '.$href);
            }
        }
        $checked++;
    }
}
foreach (['WF-017', 'WF-018', 'WF-019', 'WF-020', 'WF-021'] as $id) {
    if ($documents['WORKFLOW-INDEX.html']->query('//tr[td[1][normalize-space()="'.$id.'"]]')->length !== 1) {
        throw new RuntimeException($id.' must appear exactly once as a workflow table row.');
    }
}
foreach (['EXP-SE-001', 'EXP-SE-002', 'EXP-SE-003', 'EXP-SE-004', 'HR-ATT-004', 'HR-PAY-005'] as $id) {
    if ($documents['SCREEN-INDEX.html']->query('//tr[td[1][normalize-space()="'.$id.'"]]')->length !== 1) {
        throw new RuntimeException($id.' must appear exactly once as a screen table row.');
    }
}
echo "PASS four guide pages; $checked internal links/anchors; stable Site Expense/approval IDs including WF-019, HR-ATT-004, WF-020, HR-PAY-005 and WF-021.\n";
