<?php

declare(strict_types=1);

namespace BelKoD\OdtGenerator\Tests;

use BelKoD\OdtGenerator\Ir\BlockNode;
use BelKoD\OdtGenerator\Ir\CellNode;
use BelKoD\OdtGenerator\Ir\DocumentNode;
use BelKoD\OdtGenerator\Ir\HeadingNode;
use BelKoD\OdtGenerator\Ir\ListNode;
use BelKoD\OdtGenerator\Ir\ParagraphNode;
use BelKoD\OdtGenerator\Ir\TableNode;
use BelKoD\OdtGenerator\PipelineGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Тесты двухфазного конвейера DOM -> IR -> Render.
 * Ключевое: ни один этап не использует передачу массива по ссылке.
 */
class IrPipelineTest extends TestCase
{
    private PipelineGenerator $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = new PipelineGenerator();
    }

    public function testParagraphIsMappedToParagraphNode(): void
    {
        $ir = $this->pipeline->map('<p>Hello</p>');
        $this->assertCount(1, $ir->getChildren());
        $this->assertInstanceOf(ParagraphNode::class, $ir->getChildren()[0]);
    }

    public function testDisplayNoneIsSkipped(): void
    {
        $ir = $this->pipeline->map('<div style="display:none"><p>Hidden</p></div><p>Visible</p>');
        $children = $ir->getChildren();
        $this->assertCount(1, $children);
        $this->assertStringContainsString('Visible', (new \BelKoD\OdtGenerator\Rendering\OdtRenderer())->render($children[0] instanceof DocumentNode ? $children[0] : new DocumentNode([$children[0]])));
    }

    public function testNestedTablesAreSupported(): void
    {
        $html = '<table><tr><td><table><tr><td>Deep</td></tr></table></td></tr></table>';
        $ir = $this->pipeline->map($html);

        /** @var TableNode $outer */
        $outer = $ir->getChildren()[0];
        $this->assertInstanceOf(TableNode::class, $outer);

        $cell = $outer->getRows()[0]->getCells()[0];
        $this->assertInstanceOf(CellNode::class, $cell);

        $inner = $cell->getBlocks()[0];
        $this->assertInstanceOf(TableNode::class, $inner);
        $this->assertSame(1, $inner->getColumnCount());
    }

    public function testColspanRowspanGridCalculation(): void
    {
        $html = '<table><tr><td colspan="2">A</td><td rowspan="2">B</td></tr><tr><td>C</td></tr></table>';
        $ir = $this->pipeline->map($html);
        /** @var TableNode $table */
        $table = $ir->getChildren()[0];
        // colspan 2 + rowspan-occupied column => 3 columns total
        $this->assertSame(3, $table->getColumnCount());
    }

    public function testNestedLists(): void
    {
        $html = '<ul><li>A<ol><li>B</li></ol></li></ul>';
        $ir = $this->pipeline->map($html);
        /** @var ListNode $ul */
        $ul = $ir->getChildren()[0];
        $this->assertInstanceOf(ListNode::class, $ul);
        $blocks = $ul->getItems()[0]->getBlocks();
        $this->assertContainsOnlyInstancesOf(BlockNode::class, $blocks);
    }

    public function testHeadingsAndInlineStyles(): void
    {
        $result = $this->pipeline->generate('<h2>Title <b>x</b></h2>');
        $this->assertStringContainsString('<text:h text:outline-level="2">', $result['body']);
        $this->assertStringContainsString('</text:h>', $result['body']);
    }

    public function testOutputIsValidXmlFragment(): void
    {
        $result = $this->pipeline->generate(
            '<p>a &amp; b < "c" ></p><table><tr><td>x</td></tr></table>'
        );
        $wrapped = '<root xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
            . ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
            . ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">'
            . $result['body'] . '</root>';
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($wrapped));
    }

    public function testStyleNamesAreDeterministic(): void
    {
        $html = '<span style="color:red">a</span><span style="color:red">b</span>';
        $r1 = $this->pipeline->generate($html);
        $r2 = $this->pipeline->generate($html);
        $this->assertSame($r1['styles'], $r2['styles']);
        // Одинаковый CSS => один и тот же стиль, дедуплицирован в реестре.
        $this->assertSame(1, substr_count($r1['styles'], 'style:family="text"'));
    }

    public function testNoByReferenceSignaturesInNewCode(): void
    {
        $files = array_merge(
            glob(__DIR__ . '/../src/Ir/*.php'),
            glob(__DIR__ . '/../src/Mapping/*.php'),
            glob(__DIR__ . '/../src/Rendering/*.php'),
            [__DIR__ . '/../src/PipelineGenerator.php']
        );
        foreach ($files as $file) {
            $this->assertStringNotContainsString('&$', file_get_contents($file), "By-ref found in {$file}");
        }
    }
}
