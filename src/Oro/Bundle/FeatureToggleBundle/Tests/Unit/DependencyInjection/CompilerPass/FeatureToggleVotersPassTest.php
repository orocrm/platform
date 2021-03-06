<?php

namespace Oro\Bundle\FeatureToggleBundle\Tests\Unit\DependencyInjection\CompilerPass;

use Oro\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass\FeatureToggleVotersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FeatureToggleVotersPassTest extends \PHPUnit\Framework\TestCase
{
    /** @var FeatureToggleVotersPass */
    private $compiler;

    protected function setUp(): void
    {
        $this->compiler = new FeatureToggleVotersPass();
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();
        $featureCheckerDef = $container->register('oro_featuretoggle.checker.feature_checker');

        $container->register('voter_1')
            ->addTag('oro_featuretogle.voter', ['priority' => 100]);
        $container->register('voter_2')
            ->addTag('oro_featuretogle.voter');
        $container->register('voter_3')
            ->addTag('oro_featuretogle.voter', ['priority' => -100]);

        $this->compiler->process($container);

        self::assertEquals(
            [
                ['setVoters', [[new Reference('voter_3'), new Reference('voter_2'), new Reference('voter_1')]]]
            ],
            $featureCheckerDef->getMethodCalls()
        );
    }

    public function testProcessWhenNoVoters()
    {
        $container = new ContainerBuilder();
        $featureCheckerDef = $container->register('oro_featuretoggle.checker.feature_checker');

        $this->compiler->process($container);

        self::assertEquals(
            [
                ['setVoters', [[]]]
            ],
            $featureCheckerDef->getMethodCalls()
        );
    }
}
