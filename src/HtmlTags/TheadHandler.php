<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

/**
 * Генератор THEAD.
 */
class TheadHandler extends TagHandler implements BlockInterface
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
                $trHandler = new TrHandler($this->factory, ['maxCols' => 0]); // maxCols будет переопределён выше
                $trHandler->setFactory($this->factory);
                $trOutput = [];
                $trHandler->handle($child, $trOutput);
                if (!empty($output)) {
                    $result .= implode('', $output);
                }
            }
        }
        return $result;
    }
}