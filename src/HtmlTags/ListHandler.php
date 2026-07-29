<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\OdtGenerator;
use BelKoD\OdtGenerator\StyleGenerator;

/**
 * Генератор списка, в том числе вложенного.
 */
class ListHandler extends TagHandler
{
    /**
     * @var OdtGenerator
     */
    private $generator;
    /**
     * @var bool
     */
    private $isOrdered;
    /**
     * @var string
     */
    private $styleName = '';
    /**
     * @var int|mixed
     */
    private $level = 0;

    public function __construct($factory, int $level = 0)
    {
        $this->generator = $factory->getGenerator();
        $this->factory = $factory;
        $this->level = $level;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $tagName = \strtolower($node->tagName);
        $this->isOrdered = ($tagName === 'ol');
        // Выбираем стиль списка
        $listStyleName = $this->isOrdered ? 'OrderedList' : 'UnorderedList';

        if ($this->level == 0) {
            $this->styleName = $this->style($node);
            $listXml = '<text:list text:style-name="' . $listStyleName . '">';
        } else {
            $listXml = '<text:list text:continue-numbering="false">';
        }
        $listXml .= $this->build($node);
        $listXml .= '</text:list>';

        $paragraphs[] = $listXml;
    }

    /**
     * @inheritDoc
     */
    protected function build(\DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_ELEMENT_NODE && strtolower($child->tagName) === 'li') {
                $liContent = $this->sub_build($child);
                $items[] = '<text:list-item>' . $liContent . '</text:list-item>';;
            }
        }
        if (!empty($items)) {
            $result .= implode('', $items);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function style(\DOMNode $node, array $options = [])
    {
        $tagName = strtolower($node->tagName);
        $key = md5(rand(1,100).strtoupper($node->tagName) . '_para');
        $baseStyleName = 'StandardParagraph';
        if ($tagName == 'ol') {
            $baseStyleName = 'OrderStyle_' . substr($key, 0, 8);
            $this->factory->getGenerator()->addAutomaticStyle(
                '<style:style style:name="'.$baseStyleName.'" style:family="paragraph" style:parent-style-name="StandardParagraph" style:list-style-name="OrderedList"/>'
            );
        } elseif ($tagName == 'ul') {
            $baseStyleName = 'UnOrderStyle_' . substr($key, 0, 8);
            $this->factory->getGenerator()->addAutomaticStyle(
                '<style:style style:name="'.$baseStyleName.'" style:family="paragraph" style:parent-style-name="StandardParagraph" style:list-style-name="UnOrderedList"/>'
            );
        }
        $options['forParagraph'] = true;
        $options['parentStyleName'] = $baseStyleName;
        if (empty($styleName = parent::style($node, [$options]))) {
            $styleName = $baseStyleName;
        }
        return $styleName;
    }

    /**
     * Обработка дочерних узлов.
     *
     * @param \DOMNode $Node
     * @return string
     * @throws \Exception
     */
    private function sub_build(\DOMNode $Node)
    {
        $contentParts = [];

        foreach ($Node->childNodes as $child) {
            if ($child->nodeType === \XML_TEXT_NODE) {
                $styleName = parent::style($Node, ['forParagraph' => true, 'parentStyleName' => $this->styleName]);
                $contentParts[] = '<text:p text:style-name="'.$styleName.'">' .
                    \htmlspecialchars($child->nodeValue, \ENT_NOQUOTES, 'UTF-8') .
                    '</text:p>';
            } elseif ($child->nodeType === \XML_ELEMENT_NODE) {
                $tagName = strtolower($child->tagName);
                if (in_array($tagName, ['ul', 'ol'])) {
                    $nestedList = [];
                    $handler = $this->factory->getHandler($child, ['level' => ($this->level + 1)]);
                    //$handler = new ListHandler($this->generator, $tagName, $this->factory, ($this->level + 1));
                    $handler->handle($child, $nestedList);
                    if (!empty($nestedList)) {
                        $contentParts[] = implode('', $nestedList);
                    }
                } else {
                    $handler = $this->factory->getHandler($child);
                    $output = [];
                    $handler->handle($child, $output);
                    if (!empty($output)) {
                        $contentParts[] = '<text:p text:style-name="'.$this->styleName.'">' .
                            implode('', $output) .
                            '</text:p>';
                    }
                }
            }
        }

        $pContent = implode('', $contentParts);
        return $pContent;
    }
}