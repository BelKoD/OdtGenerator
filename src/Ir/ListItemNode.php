<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Пункт списка <li>.
 */
final class ListItemNode extends Node
{
    /** @var BlockNode[] */
    private $blocks;

    /**
     * @param BlockNode[] $blocks
     */
    public function __construct(array $blocks, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->blocks = $blocks;
    }

    /**
     * @return BlockNode[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }
}
