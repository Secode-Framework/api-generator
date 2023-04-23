<?php

namespace Secode\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Secode\Parser\Parser;

class ParserTest extends TestCase
{
    public function testWhenParserOpenApi()
    {
        $this->expectNotToPerformAssertions();
        $parser = new Parser();
        $parser->setControllerInterfacePath(dirname(__DIR__, 2) . "/tests/resources/php2");
        $parser->setControllerNamespace("tests\\resources\\php2");
        $parser->setDtoClassPath(dirname(__DIR__, 2) . "/tests/resources/php");
        $parser->setApiRoutesYmlPath(dirname(__DIR__, 2) . "/tests/resources");
        $parser->ymlToCode(dirname(__DIR__) . '/resources/apifirst.yml');
    }
}
