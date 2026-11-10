<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Ячейка таблицы. Содержит блоки (абзацы, вложенные таблицы, списки),
 * что позволяет корректно поддерживать рекурсивную вложенность таблиц.
 */
final class CellNode extends Node
{
    /** @var BlockNode[] */
    private $blocks;

    /** @var int */
    private $colSpan;

    /** @var int */
    private $rowSpan;

    /** @var bool */
    private $isHeader;

    /**
     * @param BlockNode[] $blocks
     */
    public function __construct(
        array $blocks,
        int $colSpan = 1,
        int $rowSpan = 1,
        bool $isHeader = false,
        ?string $styleName = null
    ) {
        parent::__construct($styleName);
        $this->blocks = $blocks;
        $this->colSpan = max(1, $colSpan);
        $this->rowSpan = max(1, $rowSpan);
        $this->isHeader = $isHeader;
    }

    /**
     * @return BlockNode[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    public function getColSpan(): int
    {
        return $this->colSpan;
    }

    public function getRowSpan(): int
    {
        return $this->rowSpan;
    }

    public function isHeader(): bool
    {
        return $this->isHeader;
    }
}
