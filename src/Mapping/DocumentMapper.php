<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Mapping;

use BelKoD\OdtGenerator\Ir\BlockNode;
use BelKoD\OdtGenerator\Ir\CellNode;
use BelKoD\OdtGenerator\Ir\DocumentNode;
use BelKoD\OdtGenerator\Ir\HeadingNode;
use BelKoD\OdtGenerator\Ir\InlineNode;
use BelKoD\OdtGenerator\Ir\LineBreakNode;
use BelKoD\OdtGenerator\Ir\ListItemNode;
use BelKoD\OdtGenerator\Ir\ListNode;
use BelKoD\OdtGenerator\Ir\ParagraphNode;
use BelKoD\OdtGenerator\Ir\RowNode;
use BelKoD\OdtGenerator\Ir\SpanNode;
use BelKoD\OdtGenerator\Ir\TableNode;
use BelKoD\OdtGenerator\Ir\TextNode;
use BelKoD\OdtGenerator\StyleHelper;

/**
 * Фаза 1: DOM -> IR.
 *
 * Чистые функции-мапперы: принимают \DOMNode и неизменяемый ParseContext,
 * возвращают узел IR. Никаких побочных эффектов, никакой передачи массивов
 * по ссылке, никакого мутации входного DOM.
 *
 * Стили (automatic styles) на этой фазе НЕ создаются — здесь вычисляется
 * только канонический набор CSS-свойств узла; их регистрацией занимается
 * StyleRegistry на фазе рендеринга.
 */
final class DocumentMapper
{
    /** Теги, содержимое которых пропускается целиком. */
    private const IGNORED_TAGS = ['html', 'body', 'head', 'meta', 'title', 'script', 'style', 'link'];

    /** Блочные теги. */
    private const BLOCK_TAGS = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'blockquote', 'pre', 'hr',
    ];

    /** Strочные теги форматирования, маппятся как span. */
    private const INLINE_SPAN_TAGS = [
        'span', 'b', 'strong', 'i', 'em', 'u', 'small', 'mark', 'del', 'ins', 'sub', 'sup', 'a', 'font',
    ];

    /**
     * Точка входа: преобразует DOM-документ в дерево IR.
     */
    public function mapDocument(\DOMNode $dom): DocumentNode
    {
        // Спускаемся к <body> (или к документу, если body нет), игнорируя обёртки html/head.
        $root = $dom;
        if ($dom instanceof \DOMDocument) {
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body !== null) {
                $root = $body;
            } elseif ($dom->documentElement !== null) {
                $root = $dom->documentElement;
            }
        }
        return new DocumentNode($this->mapBlockSequence($root, ParseContext::create()));
    }

    /**
     * Мапит последовательность детей узла в список блочных IR-узлов,
     * «поднимая» голый inline-контент в неявные абзацы.
     *
     * @return BlockNode[]
     */
    private function mapBlockSequence(\DOMNode $parent, ParseContext $ctx): array
    {
        $blocks = [];
        $inlineBuffer = [];

        foreach ($parent->childNodes as $child) {
            if ($this->isIgnored($child)) {
                continue;
            }

            if ($child->nodeType === \XML_ELEMENT_NODE && $this->isBlockTag($child)) {
                // Только НЕпустые inline-фрагменты образуют неявный абзац.
                if ($this->hasVisibleContent($inlineBuffer)) {
                    $blocks[] = new ParagraphNode($this->normalizeInlines($inlineBuffer));
                }
                $inlineBuffer = [];

                if ($this->isTransparentContainer($child)) {
                    if (!$this->isVisible($this->css($child))) {
                        continue; // display:none у контейнера скрывает всё содержимое
                    }
                    // div/blockquote и т.п.: рекурсивно поднимаем его блоки наверх.
                    foreach ($this->mapBlockSequence($child, $ctx) as $inner) {
                        $blocks[] = $inner;
                    }
                    continue;
                }
                $block = $this->mapBlock($child, $ctx);
                if ($block !== null) {
                    $blocks[] = $block;
                }
            } else {
                $inline = $this->mapInline($child, $ctx);
                if ($inline !== null) {
                    $inlineBuffer[] = $inline;
                }
            }
        }

        if ($this->hasVisibleContent($inlineBuffer)) {
            $blocks[] = new ParagraphNode($this->normalizeInlines($inlineBuffer));
        }

        return $blocks;
    }

    /**
     * Маппинг одного блочного элемента. Возвращает null для display:none.
     */
    private function mapBlock(\DOMNode $node, ParseContext $ctx): ?BlockNode
    {
        $css = $this->css($node);
        if (!$this->isVisible($css)) {
            return null;
        }

        $tag = strtolower($node->nodeName);
        $styleName = $this->styleKeyFor($css);

        switch ($tag) {
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                $hInlines = $this->normalizeInlines($this->mapInlineSequence($node, $ctx));
                return $this->hasVisibleContent($hInlines)
                    ? new HeadingNode((int) substr($tag, 1), $hInlines, $styleName)
                    : null;

            case 'ul':
            case 'ol':
                return $this->mapList($node, $ctx, $tag === 'ol' ? ListNode::TYPE_ORDERED : ListNode::TYPE_UNORDERED, $styleName);

            case 'li':
                // <li> вне контекста маппится как блок напрямую (редкий случай).
                return new ParagraphNode($this->normalizeInlines($this->mapInlineSequence($node, $ctx)), $styleName);

            case 'table':
                return $this->mapTable($node, $ctx, $styleName);

            case 'thead':
            case 'tbody':
            case 'tfoot':
            case 'tr':
            case 'td':
            case 'th':
                // Вне контекста таблицы не маппятся (внутри таблицы обрабатываются mapTable/mapRow напрямую).
                return null;

            case 'hr':
                return new ParagraphNode([new TextNode('')], $styleName);

            default:
                // p, pre и прочие «простые» блочные — абзац с inline-содержимым.
                $inlines = $this->normalizeInlines($this->mapInlineSequence($node, $ctx));
                return $this->hasVisibleContent($inlines)
                    ? new ParagraphNode($inlines, $styleName)
                    : null;
        }
    }

    private function mapList(\DOMNode $node, ParseContext $ctx, string $type, ?string $styleName): ListNode
    {
        $items = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== \XML_ELEMENT_NODE || strtolower($child->nodeName) !== 'li') {
                continue;
            }
            $css = $this->css($child);
            if (!$this->isVisible($css)) {
                continue;
            }
            $items[] = new ListItemNode(
                $this->mapBlockSequence($child, $ctx),
                $this->styleKeyFor($css)
            );
        }
        return new ListNode($type, $items, $styleName);
    }

    /**
     * Маппинг таблицы + локальный расчёт сетки (colspan/rowspan) без
     * глобального сканирования DOM. Вложенные таблицы рекурсивно
     * получают новую область видимости через ParseContext::pushScope().
     */
    private function mapTable(\DOMNode $node, ParseContext $ctx, ?string $styleName): TableNode
    {
        $css = $this->css($node);

        // Область видимости стилей таблицы для потомков (ячеек, вложенных таблиц).
        $cellCtx = $ctx->pushScope([
            'tableBorder' => $css['border'] ?? $ctx->option('tableBorder'),
            'tablePadding' => $ctx->option('tablePadding'),
        ]);

        $rows = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== \XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag === 'tr') {
                $row = $this->mapRow($child, $cellCtx);
                if ($row !== null) {
                    $rows[] = $row;
                }
            } elseif (in_array($tag, ['thead', 'tbody', 'tfoot'], true)) {
                if (!$this->isVisible($this->css($child))) {
                    continue;
                }
                foreach ($child->childNodes as $trCandidate) {
                    if ($trCandidate->nodeType === \XML_ELEMENT_NODE && strtolower($trCandidate->nodeName) === 'tr') {
                        $row = $this->mapRow($trCandidate, $cellCtx);
                        if ($row !== null) {
                            $rows[] = $row;
                        }
                    }
                }
            }
        }

        return new TableNode($rows, $this->computeColumnCount($rows), $styleName);
    }

    private function mapRow(\DOMNode $node, ParseContext $ctx): ?RowNode
    {
        $css = $this->css($node);
        if (!$this->isVisible($css)) {
            return null;
        }

        $cells = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== \XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if ($tag !== 'td' && $tag !== 'th') {
                continue;
            }
            $cellCss = $this->css($child);
            if (!$this->isVisible($cellCss)) {
                continue;
            }

            $cells[] = new CellNode(
                $this->mapCellBlocks($child, $ctx),
                (int) max(1, (int) $child->getAttribute('colspan')),
                (int) max(1, (int) $child->getAttribute('rowspan')),
                $tag === 'th',
                $this->styleKeyFor($cellCss)
            );
        }

        return empty($cells) ? null : new RowNode($cells, $this->styleKeyFor($css));
    }

    /**
     * Содержимое ячейки: если есть явные блоки (<p>, <table>...) — берём их;
     * иначе весь inline-контент заворачивается в один абзац.
     *
     * @return BlockNode[]
     */
    private function mapCellBlocks(\DOMNode $cell, ParseContext $ctx): array
    {
        $blocks = $this->mapBlockSequence($cell, $ctx);
        return $blocks;
    }

    /**
     * @param RowNode[] $rows
     */
    private function computeColumnCount(array $rows): int
    {
        // Сетка с учётом rowspan: occupancy-матрица.
        $occupied = []; // [rowIndex][colIndex] = true (занято растянутой сверху ячейкой)
        $maxCols = 0;

        foreach ($rows as $r => $row) {
            $c = 0;
            foreach ($row->getCells() as $cell) {
                while (isset($occupied[$r][$c])) {
                    $c++;
                }
                for ($dc = 0; $dc < $cell->getColSpan(); $dc++) {
                    for ($dr = 0; $dr < $cell->getRowSpan(); $dr++) {
                        $occupied[$r + $dr][$c + $dc] = true;
                    }
                }
                $c += $cell->getColSpan();
            }
            $maxCols = max($maxCols, $c);
        }

        return $maxCols;
    }

    /* ---------------------------------------------------------------------
     * Inline-маппинг
     * ------------------------------------------------------------------ */

    /**
     * @return InlineNode[]
     */
    private function mapInlineSequence(\DOMNode $parent, ParseContext $ctx): array
    {
        $inlines = [];
        foreach ($parent->childNodes as $child) {
            $inline = $this->mapInline($child, $ctx);
            if ($inline !== null) {
                $inlines[] = $inline;
            }
        }
        return $inlines;
    }

    private function mapInline(\DOMNode $node, ParseContext $ctx): ?InlineNode
    {
        if ($node->nodeType === \XML_TEXT_NODE) {
            $text = preg_replace('/\s+/u', ' ', $node->nodeValue ?? '');
            if ($text === '' || $text === null) {
                return null;
            }
            return new TextNode($text);
        }

        if ($node->nodeType !== \XML_ELEMENT_NODE || $this->isIgnored($node)) {
            return null;
        }

        $tag = strtolower($node->nodeName);
        $css = $this->css($node);
        if (!$this->isVisible($css)) {
            return null;
        }

        if ($tag === 'br') {
            return new LineBreakNode($this->styleKeyFor($css));
        }

        if (in_array($tag, self::INLINE_SPAN_TAGS, true)) {
            return new SpanNode(
                $this->mapInlineSequence($node, $ctx),
                $this->styleKeyFor($css)
            );
        }

        // Неизвестный/странный элемент: разворачиваем его детей как inline.
        $children = $this->mapInlineSequence($node, $ctx);
        return count($children) === 1 ? $children[0] : (empty($children) ? null : new SpanNode($children, $this->styleKeyFor($css)));
    }

    /**
     * Убирает ведущие/пустые текстовые фрагменты, схлопывает соседние одинаковые span'ы.
     *
     * @param InlineNode[] $inlines
     * @return InlineNode[]
     */
    private function normalizeInlines(array $inlines): array
    {
        $result = [];
        foreach ($inlines as $inline) {
            if ($inline instanceof TextNode && trim($inline->getText()) === '') {
                if (empty($result)) {
                    continue; // ведущий whitespace отбрасываем
                }
            }
            $last = end($result);
            if (
                $inline instanceof TextNode && $last instanceof TextNode
                && $inline->getStyleName() === $last->getStyleName()
            ) {
                $result[count($result) - 1] = new TextNode($last->getText() . $inline->getText(), $last->getStyleName());
                continue;
            }
            $result[] = $inline;
        }
        return $result;
    }

    /* ---------------------------------------------------------------------
     * Хелперы
     * ------------------------------------------------------------------ */

    /**
     * @return array<string, string>
     */
    private function css(\DOMNode $node): array
    {
        if (!$node instanceof \DOMElement || !$node->hasAttribute('style')) {
            return [];
        }
        return StyleHelper::parseCss($node->getAttribute('style'));
    }

    private function isVisible(array $css): bool
    {
        return !isset($css['display']) || strpos(strtolower($css['display']), 'none') === false;
    }

    /**
     * Канонический ключ стиля из CSS-свойств (детерминированное имя вместо md5(rand())).
     * Равный CSS => равный ключ => дедупликация на стороне StyleRegistry.
     */
    private function styleKeyFor(array $css): ?string
    {
        if (empty($css)) {
            return null;
        }
        ksort($css);
        return 'Auto_' . substr(md5(json_encode($css, JSON_UNESCAPED_UNICODE)), 0, 10);
    }

    private function isIgnored(\DOMNode $node): bool
    {
        return $node->nodeType === \XML_ELEMENT_NODE
            && in_array(strtolower($node->nodeName), self::IGNORED_TAGS, true);
    }

    private function isBlockTag(\DOMNode $node): bool
    {
        if ($node->nodeType !== \XML_ELEMENT_NODE) {
            return false;
        }
        if (in_array(strtolower($node->nodeName), self::BLOCK_TAGS, true)) {
            return true;
        }
        // display:block превращает любой тег в блок.
        $css = $this->css($node);
        return isset($css['display']) && in_array($css['display'], ['block', 'table'], true);
    }

    /**
     * Есть ли в последовательности видимый контент (не только whitespace)?
     *
     * @param InlineNode[] $inlines
     */
    private function hasVisibleContent(array $inlines): bool
    {
        foreach ($inlines as $inline) {
            if ($inline instanceof TextNode) {
                if (trim($inline->getText()) !== '') {
                    return true;
                }
            } elseif ($inline instanceof LineBreakNode) {
                return true;
            } elseif ($inline instanceof SpanNode) {
                if ($this->hasVisibleContent($inline->getChildren())) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * «Прозрачные» контейнеры: сами не имеют собственного представления в ODT,
     * их блочные дети поднимаются на уровень родителя. p, h1-h6, ul, table — нет,
     * они маппятся в конкретные IR-узлы.
     */
    private function isTransparentContainer(\DOMNode $node): bool
    {
        return $node->nodeType === \XML_ELEMENT_NODE
            && in_array(strtolower($node->nodeName), ['div', 'blockquote', 'figure', 'section', 'article', 'main', 'aside', 'header', 'footer', 'form', 'center'], true);
    }
}
