<?php

class SieveCreatorTest extends CTestCase
{
    public function testRules()
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
        $this->assertFailGenerate('rule994');
        $this->assertFailGenerate('rule993');
        $this->assertFailGenerate('rule992');
        $this->assertFailGenerate('rule991');
        $this->assertFailGenerate('rule990');
        $this->assertFailGenerate('rule989');
    }

    public function testMerges()
    {
        $this->assertMerge('rule1', 'rule2');
        $this->assertMerge('rule2', 'rule3');
        $this->assertMerge('rule3', 'rule2');
        $this->assertMerge('rule4', 'rule5');
        $this->assertMerge('rule5', 'rule6');
        $this->assertMerge('rule6', 'rule4');
    }

    public function testDeletes()
    {
        $this->assertDelete('rule12', 'rule1');
        $this->assertDelete('rule12', 'rule2');
        $this->assertDelete('rule23', 'rule2');
        $this->assertDelete('rule23', 'rule3');
        $this->assertDelete('rule32', 'rule3');
        $this->assertDelete('rule32', 'rule2');
        $this->assertDelete('rule45', 'rule4');
        $this->assertDelete('rule45', 'rule5');
        $this->assertDelete('rule56', 'rule5');
        $this->assertDelete('rule56', 'rule6');
        $this->assertDelete('rule64', 'rule6');
        $this->assertDelete('rule64', 'rule4');
    }

    public function assertGenerate($ruleName)
    {
        list($rules, $actions) = require(__DIR__ . "/../fixtures/$ruleName.php");
        $script = SieveCreator::generateSieveScript($ruleName, $rules, $actions);
        $expected = file_get_contents(__DIR__ . "/../fixtures/$ruleName.txt");
        $this->assertEquals($expected, $script);
    }

    public function assertMerge($ruleName1, $ruleName2)
    {
        $script1 = file_get_contents(__DIR__ . "/../fixtures/$ruleName1.txt");
        $script2 = file_get_contents(__DIR__ . "/../fixtures/$ruleName2.txt");
        $script = SieveCreator::mergeScripts($script1, $script2);
        $expectedRuleName = 'rule' . preg_replace("/[^0-9]/", '', $ruleName1) . preg_replace("/[^0-9]/", '', $ruleName2);
        $expected = file_get_contents(__DIR__ . "/../fixtures/$expectedRuleName.txt");

        $this->assertEquals($expected, $script);
    }

    public function assertDelete($ruleName, $deleteRuleName)
    {
        $merged = file_get_contents(__DIR__ . "/../fixtures/$ruleName.txt");
        $script = SieveCreator::removeRule($deleteRuleName, $merged);

        $expectedRuleName = 'rule' . str_replace(preg_replace("/[^0-9]/", '', $deleteRuleName), '', preg_replace("/[^0-9]/", '', $ruleName));
        $expected = file_get_contents(__DIR__ . "/../fixtures/$expectedRuleName.txt");
        $this->assertEquals($expected, $script);
    }

    public function assertFailGenerate($ruleName)
    {
        list($rules, $actions) = require(__DIR__ . "/../fixtures/$ruleName.php");
        $script = SieveCreator::generateSieveScript($ruleName, $rules, $actions);
        $expected = str_replace('#rule=', '#rule=' . $ruleName, '');
        $this->assertEquals($expected, $script);
    }
}
