<?php

class SieveCreatorTest extends CTestCase
{
    public function testActions()
    {
        $this->assertGenerate('rule1');
        $this->assertGenerate('rule2');
        $this->assertGenerate('rule3');
        $this->assertGenerate('rule4');
        $this->assertGenerate('rule5');
        $this->assertGenerate('rule6');
    }

    public function testErrors()
    {
        $this->assertFailGenerate('rule999');
        $this->assertFailGenerate('rule998');
        $this->assertFailGenerate('rule997');
        $this->assertFailGenerate('rule996');
        $this->assertFailGenerate('rule995');
    }

    public function assertGenerate($ruleName)
    {
        list($rules, $actions) = require(__DIR__."/../fixtures/$ruleName.php");
        $script = SieveCreator::generateSieveScript($ruleName, $rules, $actions);
        $expected = str_replace('#rule=', '#rule=' . $ruleName, file_get_contents(__DIR__."/../fixtures/$ruleName.txt"));
        $this->assertEquals($expected, $script);
    }

    public function assertFailGenerate($ruleName)
    {
        list($rules, $actions) = require(__DIR__."/../fixtures/$ruleName.php");
        $script = SieveCreator::generateSieveScript($ruleName, $rules, $actions);
        $expected = str_replace('#rule=', '#rule=' . $ruleName, '');
        $this->assertEquals($expected, $script);
    }
}
