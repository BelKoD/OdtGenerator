<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

/**
 * Генератор контейнера.
 */
class BodyHandler extends TagHandler implements BlockInterface
{
    public function __construct() { }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        // Контейнеры (html, body) — не добавляют контент, но обходят детей
        foreach ($node->childNodes as $child) {
            $this->factory->getGenerator()->processNode($child, $paragraphs);
        }
    }
}