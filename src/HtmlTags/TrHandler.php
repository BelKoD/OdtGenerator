<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Генератор TR.
 */
class TrHandler extends TagHandler
{
    private $maxCols;

    public function __construct($factory, $maxCols)
    {
        $this->factory = $factory;
        $this->maxCols = $maxCols;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        // Получаем "занятые" ячейки из атрибута (если есть)
        $occupied = [];
        if ($node->hasAttribute('data-occupied')) {
            $occupied = explode(',', $node->getAttribute('data-occupied'));
            $occupied = array_filter($occupied, 'strlen');
            $occupied = array_map('intval', $occupied);
        }

        $cells = [];
        $currentCol = 0;

        // Пропускаем занятые ячейки
        while (in_array($currentCol, $occupied) && $currentCol < $this->maxCols) {
            $cells[] = ''; // placeholder — будет заполнен ниже
            $currentCol++;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_ELEMENT_NODE && in_array(strtolower($child->tagName), ['td', 'th'])) {
                // Пропускаем, если ячейка занята rowspan'ом
                while (in_array($currentCol, $occupied) && $currentCol < $this->maxCols) {
                    $cells[] = '';
                    $currentCol++;
                }
                if ($currentCol >= $this->maxCols) break;

                $tdHandler = $this->factory->getHandler($child, ['availableCols' => $this->maxCols - $currentCol]);
                //$tdHandler = new TdHandler($this->factory, $this->maxCols - $currentCol);
                $cellOutput = [];
                $tdHandler->handle($child, $cellOutput);
                $cells[] = implode('', $cellOutput);

                $colspan = 1;
                if ($child->hasAttribute('colspan')) {
                    $colspan = (int)$child->getAttribute('colspan');
                }
                $currentCol += $colspan;
            }
        }

        // Заполняем оставшиеся ячейки
        while ($currentCol < $this->maxCols) {
            if (in_array($currentCol, $occupied)) {
                $cells[] = '';
            } else {
                $cells[] = '<table:table-cell><text:p></text:p></table:table-cell>';
            }
            $currentCol++;
        }

        // Заполняем пропущенные (занятые) ячейки
        foreach ($occupied as $colIndex) {
            if ($colIndex < count($cells) && $cells[$colIndex] === '') {
                $cells[$colIndex] = '<table:table-cell><text:p></text:p></table:table-cell>';
            }
        }

        $paragraphs[] = '<table:table-row>' . implode('', $cells) . '</table:table-row>';
    }
}