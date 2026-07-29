<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\StyleHelper;
use BelKoD\OdtGenerator\TagHandlerFactory;
use BelKoD\OdtGenerator\Utils\Misc;

/**
 * Базовый класс.
 */
abstract class TagHandler implements TagHandlerInterface
{
    /** @var TagHandlerFactory */
    protected $factory;

    /**
     * Создает LibreOffice XML версию узла DOM, добавляет стили.
     *
     * @param \DOMNode $node Узел HTML.
     * @param array $paragraphs Содержит в себе строки XML тегов, основанных на HTML узлах.
     * @return void
     *
     * Example:
     * не обрабатываются (пропускаются) - html,body
     * <p>Text</p> -> <text:p text:style-name="StandardParagraph">Text</text:p> -> PHandler
     * <p><b|i|em|u>Text</b|i|em|u></p> -> <text:p><text:span text:style-name="Bold|Italic|Underline|">Text</text:span></text:p> -> SpanHandler
     * <span>Text</span> -> <text:span>Text</text:span> -> SpanHandler
     * <br> -> <text:line-break/> -> BrHandler
     * <h1|h6>Text</h1|h6> -> <text:h text:outline-level="1..6">Text</text:h> -> HeadingHandle
     * <table><tr><td>Text</td></tr></table> -><table:table><table:table-column/><table:table-row><table:table-cell><text:p text:style-name="StandardParagraph">Text</text:p></table:table-cell></table:table-row></table:table> -> TableHandler
     * <tbody> -> TbodyHandler
     * <thead> ->TheadHandler
     * <tr> -> TrHandler
     * <td>Text</td> -> <table:table-cell><text:p text:style-name="StandardParagraph">Text</text:p></table:table-cell> -> TdHandler
     * <td><p>Text</p></td> - допустимая конструкция, аналогична <td>Text</td>
     * <ul><li>Text</li></ul> -> <text:list text:style-name="UnorderedList"><text:list-item><text:p>Text 1</text:p></text:list-item></text:list>
     * <ol><li>Text</li></ol> -> <text:list text:style-name="OrderedList"><text:list-item><text:p>Text 1</text:p></text:list-item></text:list>
     *
     * Поддерживаются вложенные элементы:
     * <p><span|strong></span|strong></p>
     * <h1><span|strong></span|strong></h1>
     * <ul><li><ul><li></li></ul></li></ul>
     *
     * Тег <img> пока не обрабатывается.
     *
     * Поддерживается атрибут style:
     * font-family
     * font-size
     * color
     * background-color
     * font-weight
     * font-style
     * text-decoration
     * text-align
     * line-height
     * margin (-top,-bottom...)
     * padding (-top,-bottom...)
     * display: block, inline-block, при none тег не обрабатывается
     *
     * Для таблиц доступны атрибуты: rowspan, colspan.
     * Не рабатает атрибут (стиль) width.
     */
    public function handle(\DOMNode $node, array &$paragraphs) {}

    /**
     * Обрабатывает узел DOM и извлекает данные (если нужно).
     *
     * @param \DOMNode $node Узел HTML.
     * @return string Возвращает строку XML тегов или текстовое содержимое узла.
     * @throws \Exception
     */
    protected function build(\DOMNode $node): string
    {
        $result = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_TEXT_NODE) {
                /* текстовое содержимое */
                $result .= \htmlspecialchars($child->nodeValue, \ENT_NOQUOTES, 'UTF-8');
            } elseif ($child->nodeType === \XML_ELEMENT_NODE) {
                /* Нода */
                $tagName = strtolower($child->tagName);
                $handler = $this->factory->getHandler($child);
                $output = [];
                $handler->handle($child, $output);
                if (!empty($output)) {
                    $result .= implode('', $output);
                }
            }
        }

        return $result;
    }

    /**
     * Создает стили для узла DOM на основе атрибутов и возвращает имя стиля для текущего узла.
     *
     * @param \DOMNode $node Узел HTML.
     * @param array $options Массив настроек. ['forParagraph' => true|false, 'parentStyleName' => null|string]
     * @return string|null Возвращает имя созданного стиля или NULL.
     */
    protected function style(\DOMNode $node, array $options = [])
    {
        $styleName = null;

        /* True для абзаца */
        $forParagraph = Misc::arrayExtract($options, 'forParagraph', true);
        /* Наличие наследуемого стиля */
        $parentStyleName = Misc::arrayExtract($options, 'parentStyleName', null);

        /* Определяем наличие атрибута style и обрабатываем его */
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));
            $styleName = $this->factory->getStyleGenerator()->ensureInlineStyle($css, $parentStyleName, $forParagraph);
        }

        return $styleName;
    }
}