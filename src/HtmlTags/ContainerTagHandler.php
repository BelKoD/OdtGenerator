<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\OdtGenerator;

/**
 * Генератор контейнера.
 */
class ContainerTagHandler extends TagHandler
{
    /** @var OdtGenerator */
    private $generator;

    public function __construct($factory)
    {
        $this->factory = $factory;
        $this->generator = $factory->getGenerator();
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        // Контейнеры (html, body) — не добавляют контент, но обходят детей
        foreach ($node->childNodes as $child) {
            $this->generator->processNode($child, $paragraphs);
        }
    }
}