<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Текстовый inline-узел.
 */
final class TextNode extends Node implements InlineNode
{
    /** @var string */
    private $text;

    public function __construct(string $text, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }
}
