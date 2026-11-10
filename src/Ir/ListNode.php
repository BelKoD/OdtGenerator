<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;
/**
 * Список <ul>/<ol>. Пункт списка содержит блоки — это даёт естественную
 * поддержку вложенных списков и таблиц внутри <li>.
 */
final class ListNode extends Node implements BlockNode
{
    public const TYPE_UNORDERED = 'unordered';
    public const TYPE_ORDERED = 'ordered';

    /** @var string */
    private $listType;

    /** @var ListItemNode[] */
    private $items;

    /**
     * @param ListItemNode[] $items
     */
    public function __construct(string $listType, array $items, ?string $styleName = null)
    {
        parent::__construct($styleName);
        $this->listType = $listType;
        $this->items = $items;
    }

    public function getListType(): string
    {
        return $this->listType;
    }

    /**
     * @return ListItemNode[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
