<?php
require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

$html = '<table>
    <tr><td width="5cm">Таблица 1</td></tr>
</table>';

$generator = new OdtGenerator($html, 'test_single.odt');
$generator->generate();

$odtPath = $generator->getOutputPath();
$zip = new ZipArchive();
if ($zip->open($odtPath) === true) {
    $contentXml = $zip->getFromName('content.xml');
    $zip->close();
    
    // Считаем количество таблиц
    preg_match_all('/<table:table/', $contentXml, $matches);
    echo "Количество таблиц: " . count($matches[0]) . "\n\n";
    
    // Показываем все стили таблиц
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml, $styleMatches);
    echo "=== Стили таблиц ===\n";
    foreach ($styleMatches[1] as $i => $name) {
        echo "Style: $name => " . trim($styleMatches[2][$i]) . "\n";
    }
}
