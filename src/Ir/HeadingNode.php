<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;

/**
 * Заголовок <h1>..<h6>. Блок с inline-содержимым и уровнем outline.
 */
final class HeadingNode extends Node implements BlockNode
{
    /** @var int */
    private $level;

    /** @var InlineNode[] */
    private $inlines;

    /**
     * @param InlineNode[] $inlines
     */
    public function __construct(int $level, array $inlines, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->level = $level;
        $this->inlines = $inlines;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return InlineNode[]
     */
    public function getInlines(): array
    {
        return $this->inlines;
    }
}
