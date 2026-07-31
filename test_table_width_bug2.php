<?php
require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

// Тест 1: Таблица с width атрибутом
$html_with_width = '<table width="17cm">
    <tr>
        <td width="4cm">Колонка 1</td>
        <td style="width:8cm;">Колонка 2</td>
        <td width="5cm">Колонка 3</td>
    </tr>
</table>';

echo "=== ТЕСТ 1: Таблица с width=\"17cm\" ===\n";
$generator = new OdtGenerator($html_with_width, 'test_bug1.odt');
$generator->generate();
$odtPath = $generator->getOutputPath();
$zip = new ZipArchive();
if ($zip->open($odtPath) === true) {
    $contentXml = $zip->getFromName('content.xml');
    $zip->close();
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml, $matches);
    foreach ($matches[1] as $i => $name) {
        echo "Style: $name => " . trim($matches[2][$i]) . "\n";
    }
}

// Тест 2: Таблица без width атрибута
$html_without_width = '<table>
    <tr>
        <td width="4cm">Колонка 1</td>
        <td style="width:8cm;">Колонка 2</td>
        <td width="5cm">Колонка 3</td>
    </tr>
</table>';

echo "\n=== ТЕСТ 2: Таблица БЕЗ width атрибута ===\n";
$generator2 = new OdtGenerator($html_without_width, 'test_bug2.odt');
$generator2->generate();
$odtPath2 = $generator2->getOutputPath();
$zip2 = new ZipArchive();
if ($zip2->open($odtPath2) === true) {
    $contentXml2 = $zip2->getFromName('content.xml');
    $zip2->close();
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml2, $matches2);
    foreach ($matches2[1] as $i => $name) {
        echo "Style: $name => " . trim($matches2[$i][2]) . "\n";
    }
}

// Тест 3: Две таблицы подряд - одна с width, другая без
$html_two_tables = '<table width="17cm">
    <tr><td width="4cm">Таблица 1</td></tr>
</table>
<table>
    <tr><td width="5cm">Таблица 2</td></tr>
</table>';

echo "\n=== ТЕСТ 3: Две таблицы (с width и без) ===\n";
$generator3 = new OdtGenerator($html_two_tables, 'test_bug3.odt');
$generator3->generate();
$odtPath3 = $generator3->getOutputPath();
$zip3 = new ZipArchive();
if ($zip3->open($odtPath3) === true) {
    $contentXml3 = $zip3->getFromName('content.xml');
    $zip3->close();
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml3, $matches3);
    foreach ($matches3[1] as $i => $name) {
        echo "Style: $name => " . trim($matches3[2][$i]) . "\n";
    }
}
