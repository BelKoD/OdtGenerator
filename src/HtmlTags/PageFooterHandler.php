<?php
namespace BelKoD\OdtGenerator\HtmlTags;

use BelKoD\OdtGenerator\OdtGenerator;

class PageFooterHandler extends TagHandler {
    /* @var OdtGenerator*/
    private $generator;

    public function __construct($factory)
    {
        $this->generator = $factory->getGenerator();;
        $this->factory = $factory;
    }

    public function handle(\DOMNode $node, array &$paragraphs)
    {
        //$style = $this->style($node);
        $xml = $this->build($node);
        $this->generator->setMasterStyles($xml, 'footer');
    }
}