<?php
declare(strict_types=1);

$source = __DIR__ . '/../docs/USER_GUIDE.md';
$target = __DIR__ . '/../.runtime/USER_GUIDE_PRINT.html';
$lines = file($source, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    throw new RuntimeException('Cannot read user guide');
}

function inline_markup(string $text): string
{
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $text) ?? $text;
    $text = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/\[([^]]+)]\(([^)]+)\)/u', '<a href="$2">$1</a>', $text) ?? $text;
    return $text;
}

function heading_id(string $text): string
{
    $id = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower($text)) ?? '';
    return trim($id, '-');
}

$body = '';
$paragraph = [];
$listType = null;
$table = [];

$flushParagraph = static function () use (&$body, &$paragraph): void {
    if ($paragraph) {
        $body .= '<p>' . inline_markup(implode(' ', $paragraph)) . "</p>\n";
        $paragraph = [];
    }
};
$flushList = static function () use (&$body, &$listType): void {
    if ($listType !== null) {
        $body .= '</' . $listType . ">\n";
        $listType = null;
    }
};
$flushTable = static function () use (&$body, &$table): void {
    if (!$table) return;
    $body .= "<table>\n";
    foreach ($table as $rowIndex => $cells) {
        $tag = $rowIndex === 0 ? 'th' : 'td';
        $body .= '<tr>';
        foreach ($cells as $cell) $body .= '<' . $tag . '>' . inline_markup(trim($cell)) . '</' . $tag . '>';
        $body .= "</tr>\n";
    }
    $body .= "</table>\n";
    $table = [];
};

foreach ($lines as $line) {
    $trim = trim($line);
    if (preg_match('/^\|(.+)\|$/u', $trim, $match)) {
        $flushParagraph(); $flushList();
        $cells = array_map('trim', explode('|', trim($match[1], '|')));
        $separator = true;
        foreach ($cells as $cell) if (!preg_match('/^:?-{3,}:?$/', $cell)) $separator = false;
        if (!$separator) $table[] = $cells;
        continue;
    }
    $flushTable();
    if ($trim === '') { $flushParagraph(); $flushList(); continue; }
    if ($trim === '---') { $flushParagraph(); $flushList(); $body .= "<hr>\n"; continue; }
    if (preg_match('/^(#{1,4})\s+(.+)$/u', $trim, $match)) {
        $flushParagraph(); $flushList();
        $level = strlen($match[1]);
        $body .= '<h' . $level . ' id="' . heading_id($match[2]) . '">' . inline_markup($match[2]) . '</h' . $level . ">\n";
        continue;
    }
    if (preg_match('/^-\s+(.+)$/u', $trim, $match)) {
        $flushParagraph();
        if ($listType !== 'ul') { $flushList(); $listType = 'ul'; $body .= "<ul>\n"; }
        $body .= '<li>' . inline_markup($match[1]) . "</li>\n";
        continue;
    }
    if (preg_match('/^\d+\.\s+(.+)$/u', $trim, $match)) {
        $flushParagraph();
        if ($listType !== 'ol') { $flushList(); $listType = 'ol'; $body .= "<ol>\n"; }
        $body .= '<li>' . inline_markup($match[1]) . "</li>\n";
        continue;
    }
    $paragraph[] = $trim;
}
$flushParagraph(); $flushList(); $flushTable();

$font = realpath(__DIR__ . '/../public/assets/fonts/NotoSansThai-Variable.ttf');
$fontUrl = $font ? 'file:///' . str_replace('\\', '/', $font) : '';
$html = '<!doctype html><html lang="th"><head><meta charset="utf-8"><title>คู่มือการใช้งานระบบเบิกวัสดุสำนักงานออนไลน์</title><style>
@font-face{font-family:NotoThai;src:url("' . $fontUrl . '") format("truetype");font-weight:100 900}
@page{size:A4;margin:18mm 15mm 18mm 18mm;@bottom-center{content:"หน้า " counter(page) " / " counter(pages);font-size:9pt;color:#64748b}}
*{box-sizing:border-box}body{font-family:NotoThai,"Tahoma",sans-serif;color:#172033;font-size:10.5pt;line-height:1.55;margin:0}
h1{font-size:24pt;color:#0d4f86;text-align:center;margin:28mm 0 8mm;line-height:1.25}h1+p{text-align:center;color:#475569;font-size:12pt;margin-bottom:20mm}
h2{font-size:17pt;color:#0d4f86;border-bottom:2px solid #39a7e4;padding-bottom:3px;margin:10mm 0 4mm;break-after:avoid}
h3{font-size:13pt;color:#1769aa;margin:7mm 0 2mm;break-after:avoid}h4{font-size:11pt;color:#334155;break-after:avoid}
p{margin:0 0 3.2mm;orphans:3;widows:3}ul,ol{margin:0 0 4mm;padding-left:8mm}li{margin:1mm 0;break-inside:avoid}
table{width:100%;border-collapse:collapse;margin:3mm 0 6mm;font-size:9.5pt;break-inside:auto}tr{break-inside:avoid}th,td{border:1px solid #b8c5d3;padding:2mm 2.4mm;text-align:left;vertical-align:top}th{background:#e9f3fb;color:#0d4f86;font-weight:700}
code{font-family:Consolas,monospace;background:#eef2f6;border-radius:3px;padding:0 3px;font-size:9pt}a{color:#0d4f86;text-decoration:none}hr{border:0;border-top:1px solid #cbd5e1;margin:8mm 0}
strong{font-weight:700;color:#111827}
</style></head><body>' . $body . '</body></html>';

if (file_put_contents($target, $html) === false) {
    throw new RuntimeException('Cannot write printable HTML');
}
echo $target, PHP_EOL;
