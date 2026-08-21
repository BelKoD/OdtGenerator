<?php

namespace BelKoD\OdtGenerator\Interfaces;

interface TagHandlerInterface
{
    /**
     * Создает LibreOffice XML версию узла DOM, добавляет стили.
     *
     * @param \DOMNode $node
     * @param array &$paragraphs
     * @return void
     */
    public function handle(\DOMNode $node, array &$paragraphs);
}