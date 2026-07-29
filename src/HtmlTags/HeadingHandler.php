<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Генератор заголовков H1-H6.
 */
class HeadingHandler extends TagHandler
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
        $tagName = \strtolower($node->tagName);
        $level = (int)substr($tagName, 1);
        $styleName = $this->style($node, ['forParagraph' => true]);
        $content = $this->build($node);

        if ($styleName) {
            $paragraphs[] = '<text:h text:outline-level="' . $level . '" text:style-name="' . $styleName . '">' . $content . '</text:h>';
        } else {
            $paragraphs[] = '<text:h text:outline-level="' . $level . '">' . $content . '</text:h>';
        }
    }
}