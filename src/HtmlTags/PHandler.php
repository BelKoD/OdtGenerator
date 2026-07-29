<?php

namespace BelKoD\OdtGenerator\HtmlTags;

/**
 * Генератор абзаца.
 */
class PHandler extends TagHandler
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
        $styleName = $this->style($node, ['forParagraph' => true]);
        $content = $this->build($node);

        if ($styleName) {
            $paragraphs[] = '<text:p text:style-name="' . $styleName . '">' . $content . '</text:p>';
        } else {
            $paragraphs[] = '<text:p text:style-name="StandardParagraph">' . $content . '</text:p>';
        }
    }
}