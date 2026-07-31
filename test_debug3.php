<?php
require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

$html = '<table width="17cm">
    <tr><td width="4cm">Колонка 1</td></tr>
</table>';

$generator = new OdtGenerator($html, 'test_debug3.odt');
$generator->generate();

$odtPath = $generator->getOutputPath();
echo "ODT file: $odtPath\n";

$zip = new ZipArchive();
if ($zip->open($odtPath) === true) {
    $contentXml = $zip->getFromName('content.xml');
    $zip->close();
    
    // Ищем все стили таблиц
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml, $matches);
    echo "Table styles count: " . count($matches[0]) . "\n";
    foreach ($matches[1] as $i => $name) {
        echo "Style: $name => " . trim($matches[2][$i]) . "\n";
    }
    
    // Ищем table:table
    preg_match_all('/<table:table[^>]*>/', $contentXml, $tableMatches);
    echo "\nTables:\n";
    foreach ($tableMatches[0] as $table) {
        echo "$table\n";
    }
}
