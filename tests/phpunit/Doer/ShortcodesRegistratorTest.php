<?php

class ShortcodesRegistratorTest extends WpTesting_Tests_TestCase
{

    /**
     * @var WpTesting_Doer_ShortcodesRegistrator
     */
    private $doer;

    public function setUp(): void
    {
        $this->doer = new WpTesting_Doer_ShortcodesRegistrator($this->getWpFacade(), $this->getFacade(), $this->getFacade());
    }

    public function testTestsRendered()
    {
        $result = $this->doer->renderFactory('', null, 'wpt_tests');
        $this->assertStringContainsString('EPI', $result);
        $this->assertStringContainsString('decimal', $result);
    }

    public function testTestsAndItsBackwardAliasIdentical()
    {
        $old     = $this->doer->renderFactory('', null, 'wptlist');
        $current = $this->doer->renderFactory('', null, 'wpt_tests');
        $this->assertEquals($old, $current);
    }

    public function testEmptyAttributesAsStringOrArrayAreEqual()
    {
        $old     = $this->doer->renderFactory('',       null, 'wpt_tests');
        $current = $this->doer->renderFactory(array(),  null, 'wpt_tests');
        $this->assertEquals($old, $current);
    }

    public function testBadTestsAttributeGivesAnErrorWithGuide()
    {
        $result = $this->doer->renderFactory(array('list' => 'unknown'), null, 'wpt_tests');
        $this->assertStringContainsString('error-message', $result);
        $this->assertStringContainsString('UnexpectedValueException', $result);
        $this->assertStringContainsString('See <a href="http://www.w3.org/wiki/CSS/Properties/list-style-type">', $result);
    }

    public function testNoAttributesToTestReadMoreGivesError()
    {
        $result = $this->doer->renderFactory('', null, 'wpt_test_read_more');
        $this->assertTestReadMoreNotFound($result);
    }

    public function testUnknownIdToTestReadMoreGivesError()
    {
        $result = $this->doer->renderFactory(array('id' => -1), null, 'wpt_test_read_more');
        $this->assertTestReadMoreNotFound($result);
    }

    public function testTestReadMoreRendered()
    {
        $attributes = array(
            'name'        => 'eysencks-personality-inventory-epi-extroversionintroversion',
            'start_title' => 'Qwerty',
        );
        $result = $this->doer->renderFactory($attributes, null, 'wpt_test_read_more');

        $this->assertStringContainsString('EPI', $result);
        $this->assertStringContainsString('Qwerty', $result);
    }

    private function assertTestReadMoreNotFound($result)
    {
        $this->assertStringNotContainsString('EPI',                   $result);
        $this->assertStringContainsString('UnexpectedValueException', $result);
        $this->assertStringContainsString('wpt_test_read_more',       $result);
        $this->assertStringContainsString('Can not find',             $result);
    }
}
