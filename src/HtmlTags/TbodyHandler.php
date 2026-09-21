<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

/**
 * Генератор TBODY.
 */
class TbodyHandler extends TagHandler implements BlockInterface
{

    public function __construct() { }

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
                $trHandler = new TrHandler($this->factory, ['maxCols' => 0]);
                $trHandler->setFactory($this->factory);
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