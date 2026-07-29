<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Генератор TBODY.
 */
class TbodyHandler extends TagHandler
{

    public function __construct($factory)
    {
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $paragraphs[] = $this->build($node);
    }

    /**
     * @inheritDoc
     */
    protected function build(\DOMNode $node): string
    {
        // Просто обрабатываем дочерние tr
        $result = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === \XML_ELEMENT_NODE && strtolower($child->tagName) === 'tr') {
                $trHandler = new TrHandler($this->factory, 0);
                $output = [];
                $trHandler->handle($child, $output);
                if (!empty($output)) {
                    $result .= implode('', $output);
                }
            }
        }
        return $result;
    }
}