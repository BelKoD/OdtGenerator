<?php
require_once 'vendor/autoload.php';

use BelKoD\OdtGenerator\OdtGenerator;

$html_with_width = '<table width="17cm">
    <tr>
        <td width="4cm">Колонка 1 (5cm)</td>
        <td style="width:8cm;">Колонка 2 (10cm)</td>
        <td width="5cm">Колонка 3 (7cm)</td>
    </tr>
    <tr>
        <td>Ячейка 1-1</td>
        <td>Ячейка 1-2 с более длинным текстом для проверки переноса слов</td>
        <td>Ячейка 1-3</td>
    </tr>
</table>';

$generator = new OdtGenerator($html_with_width, 'test_bug.odt');
$generator->generate();

$odtPath = $generator->getOutputPath();
echo "ODT file created: $odtPath\n";

$zip = new ZipArchive();
if ($zip->open($odtPath) === true) {
    $contentXml = $zip->getFromName('content.xml');
    $zip->close();
    
    // Ищем все стили таблиц
    preg_match_all('/<style:style style:name="([^"]+)" style:family="table">(.*?)<\/style:style>/s', $contentXml, $matches);
    
    echo "\n=== Table Styles Found ===\n";
    foreach ($matches[1] as $i => $name) {
        echo "Style name: $name\n";
        echo "Content: " . $matches[2][$i] . "\n\n";
    }
    
    // Проверяем на дублирование
    $styleNames = $matches[1];
    $duplicates = array_diff_assoc($styleNames, array_unique($styleNames));
    if (!empty($duplicates)) {
        echo "!!! DUPLICATE STYLE NAMES FOUND !!!\n";
        print_r($duplicates);
    } else {
        echo "No duplicate style names.\n";
    }
} else {
    echo "Failed to open ODT file\n";
}
