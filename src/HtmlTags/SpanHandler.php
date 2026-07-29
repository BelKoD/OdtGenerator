<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\StyleHelper;

/**
 * Генератор текстового элемента.
 */
class SpanHandler extends TagHandler
{

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $display = null;
        // Определяем display
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));
            if (isset($css['display'])) {
                $display = trim(strtolower($css['display']));
            }
        }

        $finalStyleName = $this->style($node);
        $content = $this->build($node);

        // Генерация вывода в зависимости от display и наличия стилей
        if ($finalStyleName) {
            $paragraphs[] = sprintf('%s<text:span text:style-name="%s">%s</text:span>%s',
                $display === 'block' ? '<text:line-break/>' : '',
                $finalStyleName,
                $content,
                $display === 'block' || $display === 'inline-block' ? '<text:line-break/>' : ''
            );
        } else {
            $paragraphs[] = sprintf('%s<text:span>%s</text:span>%s',
                $display === 'block' ? '<text:line-break/>' : '',
                $content,
                $display === 'block' || $display === 'inline-block' ? '<text:line-break/>' : ''
            );
        }
    }

    /**
     * @inheritDoc
     */
    protected function style(\DOMNode $node, array $options = [])
    {
        $tagName = strtolower($node->tagName);
        $baseStyleName = null;

        /* Определяем базовый стиль (Bold, Italic и т.д.). Сами стили описаны в методе HTMLToODTGenerator::buildDocumentStyles() */
        if (in_array($tagName, ['b', 'strong'])) {
            $baseStyleName = 'Bold';
        } elseif (in_array($tagName, ['i', 'em'])) {
            $baseStyleName = 'Italic';
        } elseif ($tagName === 'u') {
            $baseStyleName = 'Underline';
        } elseif ($tagName === 'del') {
            $baseStyleName = 'StrikeThrough';
        } elseif ($tagName === 'sub') {
            $baseStyleName = 'Subscript';
        } elseif ($tagName === 'sup') {
            $baseStyleName = 'Superscript';
        }

        $options['forParagraph'] = false;
        $options['parentStyleName'] = $baseStyleName;
        $finalStyleName = parent::style($node, $options) ?: $baseStyleName;

        return $finalStyleName;
    }
}