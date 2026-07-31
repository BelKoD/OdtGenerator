<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\OdtGenerator;
use BelKoD\OdtGenerator\StyleHelper;

/**
 * Генератор таблицы.
 */
class TableHandler extends TagHandler
{
    /** @var OdtGenerator */
    private $generator;

    public function __construct($factory)
    {
        $this->generator = $factory->getGenerator();
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));

            $display = Misc::arrayExtract($css, 'display');
            if ($display == 'none') {
                return;
            }
        }
        
        // Сбрасываем значения
        $this->generator->setDefaultBorder(null);
        $this->generator->setDefaultCellPadding(null);

        // Border
        if ($node->hasAttribute('border') && (int)$node->getAttribute('border') > 0) {
            $this->generator->setDefaultBorder($node->getAttribute('border'));
        }

        // Cellpadding
        if ($node->hasAttribute('cellpadding')) {
            $this->generator->setDefaultCellPadding($node->getAttribute('cellpadding'));
        }

        // Определяем максимальное количество столбцов
        $maxCols = $this->calculateMaxColumns($node);

        // Собираем все строки и информацию о ширине колонок
        $rows = [];
        $columnWidths = [];
        $this->collectRows($node, $rows, $maxCols, $columnWidths);

        if (empty($rows)) {
            return;
        }

        // Генерируем стиль таблицы
        $tableStyleName = $this->style($node);

        // Формируем XML таблицы
        $attrs = '';
        if ($tableStyleName) {
            $attrs .= ' table:style-name="' . $tableStyleName . '"';
        }

        $tableXml = '<table:table' . $attrs . '>';

        // Добавляем столбцы с учётом ширины
        // Для корректного отображения ширины в редакторах ODF нужно использовать стили колонок
        for ($i = 0; $i < $maxCols; $i++) {
            $colStyleName = null;
            
            // Если есть ширина для этой колонки, создаём стиль
            if (isset($columnWidths[$i]) && $columnWidths[$i] !== null) {
                $width = $columnWidths[$i];
                $colStyleName = 'ColStyle_' . $i . '_' . substr(md5($width), 0, 8);
                
                // Проверяем, не добавлен ли уже такой стиль
                if (!$this->generator->hasAutomaticStyle($colStyleName)) {
                    // В ODF ширина колонки указывается в style:table-column-properties
                    $colStyleXml = '<style:table-column-properties style:column-width="' . $width . '" />';
                    $this->generator->addAutomaticStyle(
                        '<style:style style:name="' . $colStyleName . '" style:family="table-column">' .
                        $colStyleXml .
                        '</style:style>',
                        $colStyleName
                    );
                }
            }
            
            $colAttrs = '';
            if ($colStyleName) {
                $colAttrs .= ' table:style-name="' . $colStyleName . '"';
            }
            // Также указываем ширину напрямую в table-column для совместимости
            if (isset($columnWidths[$i]) && $columnWidths[$i] !== null) {
                $colAttrs .= ' table:column-width="' . $columnWidths[$i] . '"';
            }
            
            $tableXml .= '<table:table-column' . $colAttrs . '/>';
        }

        // Добавляем строки
        foreach ($rows as $row) {
            $tableXml .= $row;
        }

        $tableXml .= '</table:table>';

        $paragraphs[] = $tableXml;
    }

    /**
     * Создает стили таблицы.
     *
     * @param \DOMNode $node
     * @param array $options
     * @return string|null
     */
    protected function style(\DOMNode $node, array $options = [])
    {
        $properties = [];

        $css_inline = [];
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));

            $display = Misc::arrayExtract($css, 'display');
            if ($display == 'none') {
                return;
            }

            // Обрабатываем CSS-свойства
            foreach ($css as $property => $value) {
                switch ($property) {
                    case 'table-layout':
                        $properties[] = 'style:table-layout="' . $value . '"';
                        break;
                }
            }
        }

        // Ширина таблицы
        if ($node->hasAttribute('width')) {
            $width = StyleHelper::convertToCm($node->getAttribute('width'));
            if ($width) {
                $properties[] = 'fo:width="' . $width . '"';
            }
        }

        // Расстояние между ячейками (cellspacing)
        if ($node->hasAttribute('cellspacing') && (int)$node->getAttribute('cellspacing') > 0) {
            $spacing = StyleHelper::convertToCm($node->getAttribute('cellspacing'));
            if ($spacing) {
                $properties[] = 'style:table-border-spacing="' . $spacing . '"';
                $properties[] = 'fo:border-model="separating"'; // обязательно для работы cellspacing
            }
        }

        // Если нет свойств — не генерируем стиль
        if (!empty($properties)) {
            $style = '<style:table-properties ' . implode(' ', $properties) . '/>';
            return $style;
        }

        return null;
    }

    /**
     * Подсчет кол-ва столбцов в таблице.
     *
     * @param \DOMNode $tableNode
     * @return int
     */
    private function calculateMaxColumns(\DOMNode $tableNode): int
    {
        $maxCols = 0;
        $this->iterateRows($tableNode, function ($trNode) use (&$maxCols) {
            $colCount = 0;
            foreach ($trNode->childNodes as $td) {
                if ($td->nodeType === \XML_ELEMENT_NODE && in_array(strtolower($td->tagName), ['td', 'th'])) {
                    if (StyleHelper::display($td)) {
                        $colspan = 1;
                        if ($td->hasAttribute('colspan')) {
                            $colspan = (int)$td->getAttribute('colspan');
                        }
                        $colCount += $colspan;
                    }
                }
            }
            if ($colCount > $maxCols) {
                $maxCols = $colCount;
            }
        });
        return $maxCols ?: 1;
    }

    /**
     * Собирает строки таблицы и информацию о ширине колонок.
     *
     * @param \DOMNode $tableNode
     * @param array $rows
     * @param int $maxCols
     * @param array $columnWidths Массив для хранения ширины колонок (передаётся по ссылке)
     * @return void
     */
    private function collectRows(\DOMNode $tableNode, array &$rows, int $maxCols, array &$columnWidths = [])
    {
        // Инициализируем массив ширины колонок
        $columnWidths = array_fill(0, $maxCols, null);
        
        $this->iterateRows($tableNode, function ($trNode) use (&$rows, $maxCols, &$columnWidths) {
            $trHandler = $this->factory->getHandler($trNode, ['maxCols' => $maxCols]);
            $rowOutput = [];
            $trHandler->handle($trNode, $rowOutput);
            if (!empty($rowOutput)) {
                $rows[] = implode('', $rowOutput);
            }
            
            // Собираем информацию о ширине колонок из этой строки
            $this->collectColumnWidths($trNode, $columnWidths);
        });
    }
    
    /**
     * Собирает информацию о ширине колонок из строки таблицы.
     *
     * @param \DOMNode $trNode
     * @param array $columnWidths Массив ширины колонок (передаётся по ссылке)
     * @return void
     */
    private function collectColumnWidths(\DOMNode $trNode, array &$columnWidths)
    {
        $colIndex = 0;
        
        foreach ($trNode->childNodes as $cell) {
            if ($cell->nodeType === \XML_ELEMENT_NODE && in_array(strtolower($cell->tagName), ['td', 'th'])) {
                if (!StyleHelper::display($cell)) {
                    continue;
                }
                $colspan = 1;
                if ($cell->hasAttribute('colspan')) {
                    $colspan = (int)$cell->getAttribute('colspan');
                }
                $width = null;
                
                // Проверяем атрибут width
                if ($cell->hasAttribute('width')) {
                    $width = StyleHelper::convertToCm($cell->getAttribute('width'));
                }
                
                // Проверяем style="width:..."
                if (!$width && $cell->hasAttribute('style')) {
                    $css = StyleHelper::parseCss($cell->getAttribute('style'));
                    if (isset($css['width'])) {
                        $width = StyleHelper::convertToCm($css['width']);
                    }
                }
                
                // Если найдена ширина, сохраняем её для текущей и последующих колонок (с учётом colspan)
                if ($width) { 
                    // Применяем ширину ко всем колонкам, которые занимает эта ячейка
                    for ($i = 0; $i < $colspan && $colIndex + $i < count($columnWidths); $i++) {
                        // Сохраняем только первую найденную ширину для колонки
                        if ($columnWidths[$colIndex + $i] === null) {
                            $columnWidths[$colIndex + $i] = $width;
                        }
                    }
                }
                
                $colIndex += $colspan;
            }
        }
    }

    /**
     * Итератор по строкам таблицы.
     *
     * @param \DOMNode $node
     * @param $callback
     * @return void
     */
    private function iterateRows(\DOMNode $node, $callback)
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_ELEMENT_NODE) {
                $tagName = strtolower($child->tagName);
                if ($tagName === 'tr') {
                    $callback($child);
                } elseif (in_array($tagName, ['thead', 'tbody'])) {
                    $this->iterateRows($child, $callback);
                }
            }
        }
    }
}
