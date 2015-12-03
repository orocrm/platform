<?php

namespace Oro\Bundle\GridBundle\Tests\Unit\Datagrid\Views;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\GridBundle\Datagrid\Views\AbstractViewsList;
use Oro\Bundle\GridBundle\Datagrid\Views\View;

class AbstractViewsListTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $translator;

    /** @var \PHPUnit_Framework_MockObject_MockObject|AbstractViewsList */
    protected $list;

    /**
     * Setup mocks
     */
    public function setUp()
    {
        $this->translator = $this->getMockForAbstractClass('Symfony\Component\Translation\TranslatorInterface');
        $this->list       = $this->getMockForAbstractClass(
            'Oro\Bundle\GridBundle\Datagrid\Views\AbstractViewsList',
            array($this->translator)
        );
    }

    public function tearDown()
    {
        unset($this->translator);
        unset($this->list);
    }

    /**
     * @dataProvider viewsDataProvider
     *
     * @param array $viewsArray
     * @param array $shouldContain
     * @param int   $expectedCount
     * @param array $expectedException
     */
    public function testGetList($viewsArray, $shouldContain, $expectedCount = 0, $expectedException = array())
    {
        $this->list->expects($this->once())->method('getViewsList')
            ->will($this->returnValue($viewsArray));

        if ($expectedException) {
            list($exception, $message) = $expectedException;

            $this->setExpectedException($exception, $message);
        }

        /** @var ArrayCollection $result */
        $result = $this->list->getList();
        if ($shouldContain) {
            $this->assertTrue(
                $result->exists(
                    function ($key, View $element) use ($shouldContain) {
                        return $element->getName() === $shouldContain;
                    }
                )
            );
        }

        if ($expectedCount) {
            $this->assertEquals($expectedCount, $result->count());
        }
    }

    /**
     * @return array
     */
    public function viewsDataProvider()
    {
        return array(
            'good scenario'      => array(
                'views'          => array(
                    new View('some_test_name'),
                    new View('some_another_test_name')
                ),
                'should contain' => 'some_another_test_name',
                'expected count' => 2
            ),
            'exception expected' => array(
                'views'          => array(
                    new \stdClass(),
                    new View('some_another_test_name')
                ),
                'should contain' => false,
                'expected count' => 0,
                'exception'      => array(
                    '\InvalidArgumentException',
                    'List should contains only instances of View class'
                )
            ),
        );
    }

    /**
     * test getViewByName
     */
    public function testGetViewByName()
    {
        $view1 = new View('some_test_name');
        $view2 = new View('some_another_test_name');

        $viewsArray = array($view1, $view2);
        $this->list->expects($this->once())->method('getViewsList')
            ->will($this->returnValue($viewsArray));

        $this->assertFalse($this->list->getViewByName('SOME_NOT_EXISTING'));
        $this->assertEquals($view1, $this->list->getViewByName('some_test_name'));
        $this->assertFalse($this->list->getViewByName(null));
    }

    /**
     * test toChoiceList
     */
    public function testToChoiceList()
    {
        $view1 = new View('some_test_name');
        $view2 = new View('some_another_test_name');

        $viewsArray = array($view1, $view2);
        $this->list->expects($this->once())->method('getViewsList')
            ->will($this->returnValue($viewsArray));

        $this->translator->expects($this->at(0))->method('trans')->with($this->equalTo('some_test_name'))
            ->will($this->returnValue('some_test_name_trans'));
        $this->translator->expects($this->at(1))->method('trans')->with($this->equalTo('some_another_test_name'))
            ->will($this->returnValue('some_another_test_name_trans'));

        $result = $this->list->toChoiceList();

        $this->assertCount(2, $result);
        $this->assertEquals('some_test_name', $result[0]['value']);
        $this->assertEquals('some_another_test_name', $result[1]['value']);
        $this->assertEquals('some_test_name_trans', $result[0]['label']);
        $this->assertEquals('some_another_test_name_trans', $result[1]['label']);
    }

    public function testToViewData()
    {
        $view = $this->getMockBuilder('Oro\Bundle\GridBundle\Datagrid\Views\View')
            ->disableOriginalConstructor()->getMock();

        $view->expects($this->exactly(2))->method('getName')->will($this->returnValue('test_name'));
        $this->translator->expects($this->once())->method('trans')->with($this->equalTo('test_name'))
            ->will($this->returnValue('test_name_trans'));

        $choices = array(
            'value' => 'test_name',
            'label' => 'test_name_trans'
        );

        $this->list->expects($this->once())->method('getViewsList')
            ->will($this->returnValue(array($view)));

        $result = $this->list->toViewData();
        $this->assertArrayHasKey('choices', $result);
        $this->assertNotEmpty($result['choices']);
        $this->assertEquals($choices, $result['choices'][0]);
        $this->assertArrayHasKey('views', $result);
    }
}
