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
        $css = [];
        if ($node->hasAttribute('style')) {
            $css = StyleHelper::parseCss($node->getAttribute('style'));
            /* Хак, не выводит теги со стилем display:none */
            if (!StyleHelper::is_display($css)) {
                return new IgnoredTagHandler();
            }
        }

        $options['css'] = $css;
        $tagName = \strtolower($node->tagName);

        if ($tagName === 'p') {
            $tag = new PHandler($this);
        } elseif (\in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
            $tag = new HeadingHandler($this);
        } elseif (\in_array($tagName, ['ul', 'ol'])) {
            $tag = new ListHandler($this, $options);
        } elseif ($tagName === 'span') {
            $tag = new SpanHandler($this);
        // Инлайновые теги форматирования — обрабатываем как span
        } elseif (in_array($tagName, ['b', 'i', 'u', 'strong', 'em', 'small', 'mark', 'del', 'ins', 'sub', 'sup'])) {
            $tag = new SpanHandler($this);
        } elseif ($tagName === 'br') {
            $tag = new BrHandler();
        } elseif ($tagName === 'table') {
            $tag = new TableHandler($this);
        } elseif ($tagName === 'thead') {
            $tag = new TheadHandler($this);
        } elseif ($tagName === 'tbody') {
            $tag = new TbodyHandler($this);
        } elseif (in_array($tagName, ['td', 'th'])) {
            $tag = new TdHandler($this, $options);
        } elseif ($tagName === 'tr') {
            // tr требует maxCols — будет передан из TableHandler
            // Здесь возвращаем заглушку, чтобы не падало, но реально tr обрабатывается только внутри table
            $tag = new TrHandler($this, $options);
            //$tag = new IgnoredTagHandler();
        } elseif (\in_array($tagName, ['html', 'body'])) {
            $tag = new ContainerTagHandler($this);
        } elseif ($tagName === 'np') {
            $tag = new NpHandler($this);
        } elseif ($tagName === 'htmlpageheader') {
            $tag = new PageHeaderHandler($this);
        } elseif ($tagName === 'htmlpagefooter') {
            $tag = new PageFooterHandler($this);
        } elseif ($tagName === 'img') {
            $tag = new ImgHandler($this);
        } else {
            $tag = new IgnoredTagHandler();
        }
        
        $tag->setFactory($this);
        return $tag;
    }
}
