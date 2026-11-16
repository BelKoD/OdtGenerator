<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Rendering;

use BelKoD\OdtGenerator\Ir\BlockNode;
use BelKoD\OdtGenerator\Ir\CellNode;
use BelKoD\OdtGenerator\Ir\DocumentNode;
use BelKoD\OdtGenerator\Ir\HeadingNode;
use BelKoD\OdtGenerator\Ir\InlineNode;
use BelKoD\OdtGenerator\Ir\LineBreakNode;
use BelKoD\OdtGenerator\Ir\ListNode;
use BelKoD\OdtGenerator\Ir\ParagraphNode;
use BelKoD\OdtGenerator\Ir\SpanNode;
use BelKoD\OdtGenerator\Ir\TableNode;
use BelKoD\OdtGenerator\Ir\TextNode;

/**
 * Фаза 2: IR -> ODT XML.
 *
 * Чистые рекурсивные render-функции, каждая возвращает string.
 * Никакой передачи по ссылке: единственный mutable объект — StyleRegistry,
 * который аккумулирует определения стилей и сериализуется отдельно.
 *
 * Разделение Block/Inline контрактов устраняет эвристики вида
 * `instanceof SpanHandler` внутри TdHandler: обёртка <text:p> создаётся
 * рендерером автоматически, если блок ячейки содержит только inline-контент.
 */
final class OdtRenderer
{
    /** @var StyleRegistry */
    private $styles;

    /** @var array<string, bool> Имена использованных таблиц (уникальность в пределах рендера). */
    private $usedTableNames = [];

    /** @var int Счётчик безымянных таблиц. */
    private $tableCounter = 0;

    public function __construct(?StyleRegistry $styles = null)
    {
        $this->styles = $styles ?? new StyleRegistry();
    }

    public function getStyleRegistry(): StyleRegistry
    {
        return $this->styles;
    }

    /**
     * Рендерит тело content.xml из дерева IR.
     */
    public function render(DocumentNode $document): string
    {
        $body = '';
        foreach ($document->getChildren() as $block) {
            $body .= $this->renderBlock($block);
        }
        return $body;
    }

    /* ------------------------------------------------------------------
     * Блоки
     * ---------------------------------------------------------------- */

    private function renderBlock(BlockNode $node): string
    {
        if ($node instanceof DocumentNode) {
            // Транспарентная группа (thead/tbody на верхнем уровне).
            $out = '';
            foreach ($node->getChildren() as $child) {
                $out .= $this->renderBlock($child);
            }
            return $out;
        }

        if ($node instanceof ParagraphNode) {
            return $this->renderParagraph($node);
        }

        if ($node instanceof HeadingNode) {
            $styleAttr = $node->getStyleName() !== null
                ? ' text:style-name="' . self::esc($node->getStyleName()) . '"'
                : '';
            return '<text:h' . $styleAttr . ' text:outline-level="' . $node->getLevel() . '">'
                . $this->renderInlineSequence($node->getInlines())
                . '</text:h>';
        }

        if ($node instanceof ListNode) {
            return $this->renderList($node);
        }

        if ($node instanceof TableNode) {
            return $this->renderTable($node);
        }

        return '';
    }

    private function renderParagraph(ParagraphNode $node, bool $insideListItem = false): string
    {
        $styleName = $node->getStyleName() ?? 'StandardParagraph';
        $this->styles->registerParagraph($styleName, '');

        $content = $this->renderInlineSequence($node->getInlines());

        if ($insideListItem) {
            return $content; // <text:list-item> уже даёт контекст абзаца
        }
        return '<text:p text:style-name="' . self::esc($styleName) . '">' . $content . '</text:p>';
    }

    private function renderList(ListNode $node): string
    {
        $listStyle = $node->getListType() === ListNode::TYPE_ORDERED ? 'OrderedList' : 'UnorderedList';
        $xml = '<text:list text:style-name="' . $listStyle . '">';
        foreach ($node->getItems() as $item) {
            $xml .= '<text:list-item>';
            $blocks = $item->getBlocks();
            if (empty($blocks)) {
                $xml .= '<text:p text:style-name="StandardParagraph"/>';
            }
            foreach ($blocks as $block) {
                if ($block instanceof ParagraphNode) {
                    $xml .= $this->renderParagraph($block, true);
                } else {
                    // Вложенный список или таблица внутри пункта.
                    $xml .= $this->renderBlock($block);
                }
            }
            $xml .= '</text:list-item>';
        }
        return $xml . '</text:list>';
    }

    /* ------------------------------------------------------------------
     * Таблицы (полная поддержка вложенности: cell -> blocks -> table -> ...)
     * ---------------------------------------------------------------- */

    private function renderTable(TableNode $node): string
    {
        $name = $this->uniqueTableName();
        $this->styles->registerTable($name, '');

        $xml = '<table:table table:name="' . $name . '">';
        for ($c = 0; $c < $node->getColumnCount(); $c++) {
            $xml .= '<table:table-column table:number-columns-repeated="1"/>';
        }

        foreach ($node->getRows() as $row) {
            $xml .= '<table:table-row>';
            foreach ($row->getCells() as $cell) {
                $xml .= $this->renderCell($cell);
            }
            $xml .= '</table:table-row>';
        }

        return $xml . '</table:table>';
    }

    private function renderCell(CellNode $cell): string
    {
        $attrs = '';
        if ($cell->getColSpan() > 1) {
            $attrs .= ' table:number-columns-spanned="' . $cell->getColSpan() . '"';
        }
        if ($cell->getRowSpan() > 1) {
            $attrs .= ' table:number-rows-spanned="' . $cell->getRowSpan() . '"';
        }

        $styleName = $cell->getStyleName() ?? 'StandardCell';
        $this->styles->registerTableCell($styleName, '');

        $xml = '<table:table-cell table:style-name="' . self::esc($styleName) . '"' . $attrs . ' office:value-type="string">';

        $blocks = $cell->getBlocks();
        if (empty($blocks)) {
            $xml .= '<text:p text:style-name="StandardParagraph"/>';
        }
        foreach ($blocks as $block) {
            $xml .= $this->renderBlock($block);
        }

        return $xml . '</table:table-cell>';
    }

    /**
     * Уникальное имя таблицы в пределах одного рендера (требование ODF).
     */
    private function uniqueTableName(): string
    {
        do {
            $name = 'Table_' . (++$this->tableCounter);
        } while (isset($this->usedTableNames[$name]));
        $this->usedTableNames[$name] = true;
        return $name;
    }

    /* ------------------------------------------------------------------
     * Inline
     * ---------------------------------------------------------------- */

    /**
     * @param InlineNode[] $inlines
     */
    private function renderInlineSequence(array $inlines): string
    {
        $out = '';
        foreach ($inlines as $inline) {
            $out .= $this->renderInline($inline);
        }
        return $out;
    }

    private function renderInline(InlineNode $node): string
    {
        if ($node instanceof TextNode) {
            $text = self::esc($node->getText());
            if ($node->getStyleName() === null) {
                return $text;
            }
            $this->styles->registerText($node->getStyleName(), '<style:text-properties/>');
            return '<text:span text:style-name="' . self::esc($node->getStyleName()) . '">' . $text . '</text:span>';
        }

        if ($node instanceof LineBreakNode) {
            return '<text:line-break/>';
        }

        if ($node instanceof SpanNode) {
            $inner = $this->renderInlineSequence($node->getChildren());
            if ($inner === '') {
                return '';
            }
            if ($node->getStyleName() === null) {
                return $inner;
            }
            $this->styles->registerText($node->getStyleName(), '<style:text-properties/>');
            return '<text:span text:style-name="' . self::esc($node->getStyleName()) . '">' . $inner . '</text:span>';
        }

        return '';
    }

    /**
     * Единая точка экранирования XML (проблема несогласованного escaping).
     */
    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
