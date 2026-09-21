<?php

namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

/**
 * Генератор заголовков H1-H6.
 */
class H3Handler extends TagHandler implements BlockInterface
{

    public function __construct() { }

    /**
     * @inheritDoc
     */
    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $styleName = $this->style($node, ['forParagraph' => true]);
        $content = $this->build($node);

        $style = $styleName ? sprintf('text:style-name="%s"', $styleName) : '';
        $paragraphs[] = sprintf('<text:h text:outline-level="3" %s>%s</text:h>', 
            $style, $content);
    }
}