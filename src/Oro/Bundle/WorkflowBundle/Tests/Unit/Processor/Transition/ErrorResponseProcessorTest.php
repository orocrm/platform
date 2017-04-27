<?php

namespace Oro\Bundle\WorkflowBundle\Tests\Unit\Processor\Transition;

use Oro\Bundle\WorkflowBundle\Processor\Context\TransitionContext;
use Oro\Bundle\WorkflowBundle\Processor\Transition\ErrorResponseProcessor;
use Symfony\Component\HttpFoundation\Response;

class ErrorResponseProcessorTest extends \PHPUnit_Framework_TestCase
{
    /** @var ErrorResponseProcessor */
    protected $processor;

    protected function setUp()
    {
        $this->processor = new ErrorResponseProcessor();
    }

    public function testBuildResponseFromDefinedFields()
    {
        $response = new Response();
        $response->setStatusCode(418, 'message');

        /** @var TransitionContext|\PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock(TransitionContext::class);
        $context->expects($this->once())
            ->method('hasError')
            ->willReturn(true);

        $context->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['responseCode'], ['responseMessage'])
            ->willReturnOnConsecutiveCalls(418, 'message');

        $context->expects($this->once())->method('setResult')->with($response);
        $context->expects($this->once())->method('setProcessed')->with(true);

        $this->processor->process($context);
    }

    public function testBuildResponseFromError()
    {
        $response = new Response();
        $response->setStatusCode(500, 'error message');

        /** @var TransitionContext|\PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock(TransitionContext::class);
        $context->expects($this->once())
            ->method('hasError')
            ->willReturn(true);

        $context->expects($this->exactly(2))
            ->method('get')
            ->withConsecutive(['responseCode'], ['responseMessage'])
            ->willReturn(null);

        $context->expects($this->once())->method('getError')->willReturn(new \Exception('error message'));
        $context->expects($this->once())->method('setResult')->with($response);
        $context->expects($this->once())->method('setProcessed')->with(true);

        $this->processor->process($context);
    }

    public function testSkipHasNoErrors()
    {
        /** @var TransitionContext|\PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock(TransitionContext::class);
        $context->expects($this->once())->method('hasError')->willReturn(false);
        $context->expects($this->never())->method('setResult');

        $this->processor->process($context);
    }
}
