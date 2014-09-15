<?php

namespace Oro\Bundle\FormBundle\Tests\Unit\Utils;

use Symfony\Component\Form\FormView;

use Oro\Bundle\FormBundle\Utils\FormUtils;

class FormUtilsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider optionsProvider
     *
     * @param array $expectedOptions
     * @param array $modifyOptions
     * @param array $unsetOptions
     */
    public function testReplaceField($expectedOptions = [], $modifyOptions = [], $unsetOptions = [])
    {
        $testFieldName = 'testField';
        $testTypeName  = 'testType';
        $testOptions   = ['required' => true, 'auto_initialize' => true];

        $rootForm   = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $childForm  = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $formConfig = $this->getMock('Symfony\Component\Form\FormConfigInterface');
        $formType   = $this->getMock('Symfony\Component\Form\ResolvedFormTypeInterface');

        $rootForm->expects($this->once())->method('get')->with($testFieldName)
            ->will($this->returnValue($childForm));

        $childForm->expects($this->exactly(2))->method('getConfig')
            ->will($this->returnValue($formConfig));

        $formConfig->expects($this->once())->method('getType')
            ->will($this->returnValue($formType));

        $formConfig->expects($this->once())->method('getOptions')
            ->will($this->returnValue($testOptions));

        $formType->expects($this->once())->method('getName')
            ->will($this->returnValue($testTypeName));

        $rootForm->expects($this->once())->method('add')
            ->with($testFieldName, $testTypeName, $expectedOptions);

        FormUtils::replaceField($rootForm, $testFieldName, $modifyOptions, $unsetOptions);
    }

    /**
     * @return array
     */
    public function optionsProvider()
    {
        return [
            'should pass original options except auto_initialize' => [
                ['required' => true, 'auto_initialize' => false],
                [],
                []
            ],
            'should override options'                             => [
                ['required' => false, 'auto_initialize' => false],
                ['required' => false],
                []
            ],
            'should unset options'                                => [
                ['auto_initialize' => false],
                [],
                ['required']
            ]
        ];
    }

    /**
     * @dataProvider viewVariablesProvider
     *
     * @param array        $vars
     * @param string|array $classToAppend
     * @param array        $expectedVars
     */
    public function testAddClass($vars, $classToAppend, $expectedVars)
    {
        $formView       = new FormView();
        $formView->vars = $vars;

        FormUtils::appendClass($formView, $classToAppend);
        $this->assertSame($expectedVars, $formView->vars);
    }

    /**
     * @return array
     */
    public function viewVariablesProvider()
    {
        return [
            'add single class'            => [
                '$vars'          => [],
                '$classToAppend' => 'singleClass',
                '$expectedVars'  => ['attr' => ['class' => 'singleClass']]
            ],
            'add multiple classes'        => [
                '$vars'          => [],
                '$classToAppend' => ['1stClass', '2ndClass'],
                '$expectedVars'  => ['attr' => ['class' => '1stClass 2ndClass']]
            ],
            'should append, not override' => [
                '$vars'          => ['attr' => ['class' => '1stClass'], 'another' => 'not overridden'],
                '$classToAppend' => ['2ndClass'],
                '$expectedVars'  => ['attr' => ['class' => '1stClass 2ndClass'], 'another' => 'not overridden']
            ]
        ];
    }
}
