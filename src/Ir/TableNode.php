<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Таблица. Сетка (number of columns) вычисляется маппером локально
 * для каждой таблицы с учётом colspan/rowspan и хранится здесь же —
 * без глобального сканирования DOM.
 */
final class TableNode extends Node implements BlockNode
{
    /** @var RowNode[] */
    private $rows;

    /** @var int */
    private $columnCount;

    /**
     * @param RowNode[] $rows
     */
    public function __construct(array $rows, int $columnCount, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->rows = $rows;
        $this->columnCount = max(1, $columnCount);
    }

    /**
     * @return RowNode[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getColumnCount(): int
    {
        return $this->columnCount;
    }
}
