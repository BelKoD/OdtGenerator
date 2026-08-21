<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

/**
 * Пустой генератор.
 */
class IgnoredTagHandler extends TagHandler implements BlockInterface
{
    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        // Полностью игнорируем: не добавляем текст, не обходим детей
        // Ничего не делаем
    }
}