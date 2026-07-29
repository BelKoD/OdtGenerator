<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Пустой генератор.
 */
class IgnoredTagHandler extends TagHandler
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