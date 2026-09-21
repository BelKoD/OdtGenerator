<?php
namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\Interfaces\BlockInterface;

class PageFooterHandler extends TagHandler implements BlockInterface
{
    public function __construct() { }

    public function handle(\DOMNode $node, array &$paragraphs)
    {
        $xml = $this->build($node);
        $this->factory->getGenerator()->setMasterStyles($xml, 'footer');
    }
}