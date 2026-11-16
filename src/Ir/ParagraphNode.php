<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;

/**
 * Абзац с произвольным inline-содержимым (текст, span, line-break).
 */
final class ParagraphNode extends Node implements BlockNode
{
    /** @var InlineNode[] */
    private $inlines;

    /**
     * @param InlineNode[] $inlines
     */
    public function __construct(array $inlines, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->inlines = $inlines;
    }

    /**
     * @return InlineNode[]
     */
    public function getInlines(): array
    {
        return $this->inlines;
    }
}
