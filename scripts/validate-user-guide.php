<?php

// Read-only validation of all generated guide pages, including embedded training images.
$directory = dirname(__DIR__).'/docs/user-guide/';
$files = ['index.html', 'SEERA-ERP-CURRENT-SYSTEM-USER-GUIDE.html', 'SCREEN-INDEX.html', 'WORKFLOW-INDEX.html', 'VISUAL-EXAMPLES.html', 'VISUAL-EXAMPLES-AR.html', 'CLIENT-ENTRY-UPDATES-2026-11-10.html', 'CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html'];
$documents = [];
foreach ($files as $file) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    if (! $dom->loadHTML(file_get_contents($directory.$file))) {
        throw new RuntimeException('Invalid HTML: '.$file);
    }
    $documents[$file] = new DOMXPath($dom);
    if (str_starts_with($file, 'VISUAL-EXAMPLES')) {
        $expectedLang = str_contains($file, '-AR') ? 'ar' : 'en';
        if ($dom->documentElement->getAttribute('lang') !== $expectedLang
            || $documents[$file]->query('//img')->length !== 47) {
            throw new RuntimeException('Expected 43 desktop and four mobile images in '.$file);
        }
        foreach ($documents[$file]->query('//img') as $image) {
            $src = $image->getAttribute('src');
            if (! str_starts_with($src, 'data:image/jpeg;base64,')) {
                throw new RuntimeException('Image is not self-contained: '.$file);
            }
            $bytes = base64_decode(substr($src, strlen('data:image/jpeg;base64,')), true);
            if ($bytes === false || getimagesizefromstring($bytes) === false) {
                throw new RuntimeException('Invalid embedded image: '.$file);
            }
        }
    }
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
foreach (['WF-017', 'WF-018', 'WF-019', 'WF-020', 'WF-021', 'WF-022', 'WF-023', 'WF-024', 'WF-025', 'WF-026', 'WF-027', 'WF-028'] as $id) {
    if ($documents['WORKFLOW-INDEX.html']->query('//tr[td[1][normalize-space()="'.$id.'"]]')->length !== 1) {
        throw new RuntimeException($id.' must appear exactly once as a workflow table row.');
    }
}
foreach (['EXP-SE-001', 'EXP-SE-002', 'EXP-SE-003', 'EXP-SE-004', 'HR-ATT-004', 'HR-PAY-005', 'MST-PRJ-006', 'EQP-003', 'MOB-001', 'FIN-ZAT-003', 'FIN-AR-008', 'FIN-AR-010', 'FIN-AR-011'] as $id) {
    if ($documents['SCREEN-INDEX.html']->query('//tr[td[1][normalize-space()="'.$id.'"]]')->length !== 1) {
        throw new RuntimeException($id.' must appear exactly once as a screen table row.');
    }
}
$feedback = $documents['CLIENT-FEEDBACK-RESOLUTIONS-2026-11-10.html'];
for ($number = 1; $number <= 13; $number++) {
    $id = sprintf('R10-%02d', $number);
    if ($feedback->query('//tr[td[1][normalize-space()="'.$id.'"]]')->length !== 1) {
        throw new RuntimeException($id.' must appear exactly once as a client issue table row.');
    }
}
echo 'PASS '.count($files)." guide pages; $checked internal links/anchors; 94 valid embedded JPEG images; 13 client issue rows; stable screen/workflow IDs.\n";
