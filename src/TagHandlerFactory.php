<?php

namespace BelKoD\OdtGenerator;

use BelKoD\OdtGenerator\HtmlTags\NpHandler;
use BelKoD\OdtGenerator\HtmlTags\PHandler;
use BelKoD\OdtGenerator\HtmlTags\IgnoredTagHandler;
use BelKoD\OdtGenerator\HtmlTags\ContainerTagHandler;
use BelKoD\OdtGenerator\HtmlTags\HeadingHandler;
use BelKoD\OdtGenerator\HtmlTags\ListHandler;
use BelKoD\OdtGenerator\HtmlTags\SpanHandler;
use BelKoD\OdtGenerator\HtmlTags\BrHandler;
use BelKoD\OdtGenerator\HtmlTags\TableHandler;
use BelKoD\OdtGenerator\HtmlTags\TagHandlerInterface;
use BelKoD\OdtGenerator\HtmlTags\TheadHandler;
use BelKoD\OdtGenerator\HtmlTags\TbodyHandler;
use BelKoD\OdtGenerator\HtmlTags\ThHandler;
use BelKoD\OdtGenerator\HtmlTags\TrHandler;
use BelKoD\OdtGenerator\HtmlTags\TdHandler;
use BelKoD\OdtGenerator\HtmlTags\PageHeaderHandler;
use BelKoD\OdtGenerator\HtmlTags\PageFooterHandler;
use BelKoD\OdtGenerator\HtmlTags\ImgHandler;
use BelKoD\OdtGenerator\Utils\Misc;

/**
 * Фабрика генераторов
 */
class TagHandlerFactory
{
    /** @var OdtGenerator Ссылка на ODT генератор */
    private $generator;
    /** @var StyleGenerator Ссылка на генератор стилей */
    private $styleGenerator;

    public function __construct($generator, $styleGenerator)
    {
        $this->generator = $generator;
        $this->styleGenerator = $styleGenerator;
    }

    /**
     * @return OdtGenerator
     */
    public function getGenerator(): OdtGenerator
    {
        return $this->generator;
    }

    /**
     * @return StyleGenerator
     */
    public function getStyleGenerator(): StyleGenerator
    {
        return $this->styleGenerator;
    }

    /**
     * Вызывает объект, соответсвующий тегу HTML узла.
     *
     * @param \DOMNode $node Нода узла
     * @param array $options Опциональные парметры
     * @return TagHandlerInterface
     * @throws \Exception
     */
    public function getHandler(\DOMNode $node, array $options = [])
    {
        /* Хак, не выводит теги со стилем display:none */
        if (StyleHelper::display($node) === false) {
            return new IgnoredTagHandler();
        }

        $tagName = \strtolower($node->tagName);

        if ($tagName === 'p') {
            return new PHandler($this);
        } elseif (\in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            return new HeadingHandler($this);
        } elseif (\in_array($tagName, ['ul', 'ol'])) {
            if (!$this->generator) {
                throw new \Exception("ListHandler requires OdtGenerator");
            }
            $level = Misc::arrayExtract($options, 'level', 0);
            return new ListHandler($this, $level);
        } elseif ($tagName === 'span') {
            return new SpanHandler($this);
        // Инлайновые теги форматирования — обрабатываем как span
        } elseif (in_array($tagName, ['b', 'i', 'u', 'strong', 'em', 'small', 'mark', 'del', 'ins', 'sub', 'sup'])) {
            return new SpanHandler($this);
        } elseif ($tagName === 'br') {
            return new BrHandler();
        } elseif ($tagName === 'table') {
            if (!$this->generator) {
                throw new \Exception("TableHandler requires OdtGenerator");
            }
            return new TableHandler($this);
        } elseif ($tagName === 'thead') {
            return new TheadHandler($this);
        } elseif ($tagName === 'tbody') {
            return new TbodyHandler($this);
        } elseif (in_array($tagName, ['td', 'th'])) {
            $availableCols = Misc::arrayExtract($options, 'availableCols', 999);
            return new TdHandler($this, $availableCols); // передаём фабрику, availableCols будет переопределён в TrHandler
        } elseif ($tagName === 'tr') {
            // tr требует maxCols — будет передан из TableHandler
            // Здесь возвращаем заглушку, чтобы не падало, но реально tr обрабатывается только внутри table
            $maxCols = Misc::arrayExtract($options, 'maxCols', 999);
            return new TrHandler($this, $maxCols);
            //return new IgnoredTagHandler();
        } elseif (\in_array($tagName, ['html', 'body'])) {
            if (!$this->generator) {
                throw new \Exception("ContainerTagHandler requires OdtGenerator");
            }
            return new ContainerTagHandler($this);
        } elseif ($tagName === 'np') {
            return new NpHandler($this);
        } elseif ($tagName === 'htmlpageheader') {
            if (!$this->generator) {
                throw new \Exception("PageHeaderHandler requires OdtGenerator");
            }
            return new PageHeaderHandler($this);
        } elseif ($tagName === 'htmlpagefooter') {
            if (!$this->generator) {
                throw new \Exception("PageFooterHandler requires OdtGenerator");
            }
            return new PageFooterHandler($this);
        } elseif ($tagName === 'img') {
            // пока не обрабатываем
            return new ImgHandler($this);
            //return new IgnoredTagHandler();
        } else {
            return new IgnoredTagHandler();
        }
    }
}