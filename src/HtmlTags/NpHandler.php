<?php

namespace BelKoD\OdtGenerator\HtmlTags;
/**
 * Генератор создания новой страницы.
 */
class NpHandler extends TagHandler
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
        // Создаём стиль, если ещё не создан
        $this->factory->getStyleGenerator()->ensurePageBreakStyle();

        // Добавляем пустой абзац со стилем разрыва страницы
        $paragraphs[] = '<text:p text:style-name="PageBreakParagraph"/>';
    }
}