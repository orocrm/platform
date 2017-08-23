<?php

namespace Oro\Bundle\EmailBundle\Tests\Unit\Provider;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Provider\EmailActivityListProvider;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureCheckerHolderTrait;
use Oro\Bundle\FeatureToggleBundle\Checker\FeatureToggleableInterface;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\UserBundle\Entity\User;

class EmailActivityListProviderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var EmailActivityListProvider
     */
    protected $emailActivityListProvider;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $authorizationChecker;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $tokenAccessor;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineRegistryLink;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $mailboxProcessStorageLink;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $activityAssociationHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $commentAssociationHelper;

    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $featureChecker;

    protected function setUp()
    {
        $this->doctrineHelper = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\DoctrineHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $entityNameResolver = $this->getMockBuilder('Oro\Bundle\EntityBundle\Provider\EntityNameResolver')
            ->disableOriginalConstructor()
            ->getMock();
        $router = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Routing\Router')
            ->disableOriginalConstructor()
            ->getMock();
        $configManager  = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();
        $emailThreadProvider = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\Provider\EmailThreadProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $htmlTagHelper = $this->getMockBuilder('Oro\Bundle\UIBundle\Tools\HtmlTagHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $this->doctrineRegistryLink = $this
            ->getMockBuilder('Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink')
            ->disableOriginalConstructor()
            ->getMock();
        $this->mailboxProcessStorageLink = $this
            ->getMockBuilder('Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink')
            ->disableOriginalConstructor()
            ->getMock();
        $this->activityAssociationHelper = $this
            ->getMockBuilder('Oro\Bundle\ActivityBundle\Tools\ActivityAssociationHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $this->commentAssociationHelper = $this
            ->getMockBuilder('Oro\Bundle\CommentBundle\Tools\CommentAssociationHelper')
            ->disableOriginalConstructor()
            ->getMock();
        $this->featureChecker = $this->getMockBuilder(FeatureChecker::class)
            ->setMethods(['isFeatureEnabled'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->emailActivityListProvider = new EmailActivityListProvider(
            $this->doctrineHelper,
            $this->doctrineRegistryLink,
            $entityNameResolver,
            $router,
            $configManager,
            $emailThreadProvider,
            $htmlTagHelper,
            $this->authorizationChecker,
            $this->tokenAccessor,
            $this->mailboxProcessStorageLink,
            $this->activityAssociationHelper,
            $this->commentAssociationHelper
        );
    }

    public function testGetActivityOwners()
    {
        $organization = new Organization();
        $organization->setName('Org');
        $user = new User();
        $user->setUsername('test');
        $emailUser = new EmailUser();
        $emailUser->setOrganization($organization);
        $emailUser->setOwner($user);
        $owners = [$emailUser];

        $emailMock = $this->getMockBuilder('Oro\Bundle\EmailBundle\Entity\EmailUser')
            ->setMethods(
                [
                    'getFromEmailAddress',
                    'hasOwner',
                    'getOwner',
                    'getOrganization',
                    'getActivityTargets'
                ]
            )
            ->disableOriginalConstructor()
            ->getMock();
        $emailMock->expects($this->once())
            ->method('getFromEmailAddress')
            ->willReturn($emailMock);
        $emailMock->expects($this->once())
            ->method('getOwner')
            ->willReturn($emailMock);
        $emailMock->expects($this->exactly(2))
            ->method('getOrganization')
            ->willReturn($organization);
        $emailMock->expects($this->exactly(1))
            ->method('getActivityTargets')
            ->willReturn([]);

        $activityListMock = $this->getMockBuilder('Oro\Bundle\ActivityListBundle\Entity\ActivityList')
            ->disableOriginalConstructor()
            ->getMock();
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $repository = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $this->doctrineRegistryLink
            ->expects($this->once())
            ->method('getService')
            ->willReturn($em);
        $em->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);
        $repository->expects($this->once())
            ->method('findBy')
            ->willReturn($owners);

        $activityOwnerArray = $this->emailActivityListProvider->getActivityOwners($emailMock, $activityListMock);

        $this->assertCount(1, $activityOwnerArray);
        $owner = $activityOwnerArray[0];
        $this->assertEquals($organization->getName(), $owner->getOrganization()->getName());
        $this->assertEquals($user->getUsername(), $owner->getUser()->getUsername());
    }

    public function testFeatureToggleable()
    {
        $this->assertInstanceOf(FeatureToggleableInterface::class, $this->emailActivityListProvider);

        $this->emailActivityListProvider->setFeatureChecker($this->featureChecker);
        $this->emailActivityListProvider->addFeature('email');

        $mock = $this->getMockBuilder(FeatureCheckerHolderTrait::class)->setMethods(['isFeaturesEnabled'])
            ->getMockForTrait();

        $mock->expects($this->any())
            ->method('isFeaturesEnabled')
            ->will($this->returnValue(true));

        $this->assertTrue($mock->isFeaturesEnabled());
    }
}
