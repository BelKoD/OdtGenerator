<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;

/**
 * Корневой узел документа: последовательность блочных элементов.
 */
final class DocumentNode extends Node implements BlockNode
{
    /** @var BlockNode[] */
    private $children;

    /**
     * @param BlockNode[] $children
     */
    public function __construct(array $children)
    {
        parent::__construct();
        $this->children = $children;
    }

    /**
     * @return BlockNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
