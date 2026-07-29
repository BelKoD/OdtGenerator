<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Генератор перевода строки.
 */
class BrHandler extends TagHandler
{
    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        // Генерируем ODT-тег разрыва строки
        $paragraphs[] = '<text:line-break/>';
    }
}