<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\InlineInterface;

/**
 * Генератор перевода строки.
 */
class BrHandler extends TagHandler implements InlineInterface
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