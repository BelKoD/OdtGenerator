<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Ir;

/**
 * Базовый класс узла промежуточного представления (IR).
 *
 * IR — это дерево, полностью отделённое от исходного \DOMNode.
 * Фаза маппинга (DOM -> IR) строит дерево без побочных эффектов,
 * фаза рендеринга (IR -> ODT XML) только читает его.
 */
abstract class Node
{
    /** @var string|null Имя стиля, вычисленное на фазе маппинга. */
    private $styleName;

    public function __construct(?string $styleName = null)
    {
        $this->styleName = $styleName;
    }

    public function getStyleName(): ?string
    {
        return $this->styleName;
    }
}
