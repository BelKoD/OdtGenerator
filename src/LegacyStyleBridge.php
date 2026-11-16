<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\Interfaces\OdtGeneratorInterface;

/**
 * Минимальный адаптер генератора для StyleGenerator.
 *
 * StyleGenerator ожидает объект с addAutomaticStyle()/getMasterStyles().
 * В pipeline-режиме автоматические стили тегами не создаются, поэтому
 * мост просто накапливает их в массиве (для возможной отладки), а
 * мастер-стили проксирует к основному объекту.
 *
 * @internal
 */
final class LegacyStyleBridge implements OdtGeneratorInterface
{
    /** @var PipelineOdtGenerator */
    private $owner;

    /** @var array Накопленные StyleGenerator'ом автоматические стили */
    private $styles = [];

    public function __construct(PipelineOdtGenerator $owner)
    {
        $this->owner = $owner;
    }

    public function generate(): OdtGeneratorInterface
    {
        return $this->owner->generate();
    }

    public function setGlobalSettings(array $settings = []): OdtGeneratorInterface
    {
        return $this->owner->setGlobalSettings($settings);
    }

    public function getGlobalSettings()
    {
        return $this->owner->getGlobalSettings();
    }

    public function setDefaultBorder($border): OdtGeneratorInterface
    {
        return $this->owner->setDefaultBorder($border);
    }

    public function getDefaultBorder()
    {
        return $this->owner->getDefaultBorder();
    }

    public function addAutomaticStyle(string $styleXml, $styleName = null): OdtGeneratorInterface
    {
        if ($styleName !== null && !isset($this->styles[$styleName])) {
            $this->styles[$styleName] = $styleXml;
        } else {
            $this->styles[] = $styleXml;
        }
        return $this;
    }

    public function hasAutomaticStyle(string $styleName): bool
    {
        return isset($this->styles[$styleName]);
    }

    public function setDefaultCellPadding($padding): OdtGeneratorInterface
    {
        return $this->owner->setDefaultCellPadding($padding);
    }

    public function getDefaultCellPadding()
    {
        return $this->owner->getDefaultCellPadding();
    }

    public function clearAutomaticStyles()
    {
        $this->styles = [];
        return $this;
    }

    public function getAutomaticStyles(): array
    {
        return $this->styles;
    }

    public function getMasterStyles()
    {
        return $this->owner->getMasterStyles();
    }

    public function setMasterStyles($masterStyles, string $type = 'header'): OdtGeneratorInterface
    {
        return $this->owner->setMasterStyles($masterStyles, $type);
    }

    public function getOutputPath()
    {
        return $this->owner->getOutputPath();
    }

    public function getOutputFile()
    {
        return $this->owner->getOutputFile();
    }
}
