<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Строчное форматирование <span>/<b>/<i>/<strong>/... — обёртка над inline-узлами.
 */
final class SpanNode extends Node implements InlineNode
{
    /** @var InlineNode[] */
    private $children;

    /**
     * @param InlineNode[] $children
     */
    public function __construct(array $children, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->children = $children;
    }

    /**
     * @return InlineNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
