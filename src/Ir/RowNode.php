<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Строка таблицы.
 */
final class RowNode extends Node
{
    /** @var CellNode[] */
    private $cells;

    /**
     * @param CellNode[] $cells
     */
    public function __construct(array $cells, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->cells = $cells;
    }

    /**
     * @return CellNode[]
     */
    public function getCells(): array
    {
        return $this->cells;
    }
}
