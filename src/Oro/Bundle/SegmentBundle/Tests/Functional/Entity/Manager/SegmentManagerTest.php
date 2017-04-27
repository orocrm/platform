<?php

namespace Oro\Bundle\SegmentBundle\Tests\Functional\Entity\Manager;

use Doctrine\ORM\EntityRepository;

use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentSnapshot;
use Oro\Bundle\SegmentBundle\Tests\Functional\DataFixtures\LoadSegmentData;
use Oro\Bundle\SegmentBundle\Tests\Functional\DataFixtures\LoadSegmentSnapshotData;
use Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class SegmentManagerTest extends WebTestCase
{
    /** @var SegmentManager */
    private $manager;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([
            LoadSegmentSnapshotData::class,
        ]);

        $this->manager = $this->getContainer()->get('oro_segment.segment_manager');
    }

    public function testGetSegmentTypeChoices()
    {
        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        /** @var Segment $staticSegment */
        $staticSegment = $this->getReference(LoadSegmentData::SEGMENT_STATIC);

        $this->assertEquals([
            $dynamicSegment->getType()->getName() => $dynamicSegment->getType()->getLabel(),
            $staticSegment->getType()->getName() => $staticSegment->getType()->getLabel(),
        ], $this->manager->getSegmentTypeChoices());
    }

    public function testGetSegmentByEntityName()
    {
        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        /** @var Segment $dynamicSegment */
        $dynamicSegmentWithFilter = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC_WITH_FILTER);
        /** @var Segment $staticSegment */
        $staticSegment = $this->getReference(LoadSegmentData::SEGMENT_STATIC);
        /** @var Segment $staticSegmentWithFilter */
        $staticSegmentWithFilter = $this->getReference(LoadSegmentData::SEGMENT_STATIC_WITH_FILTER_AND_SORTING);

        $this->assertEquals(
            [
                'results' => [
                    [
                        'id'   => 'segment_' . $dynamicSegment->getId(),
                        'text' => $dynamicSegment->getName(),
                        'type' => 'segment',
                    ],
                    [
                        'id'   => 'segment_' . $dynamicSegmentWithFilter->getId(),
                        'text' => $dynamicSegmentWithFilter->getName(),
                        'type' => 'segment',
                    ],
                    [
                        'id'   => 'segment_' . $staticSegment->getId(),
                        'text' => $staticSegment->getName(),
                        'type' => 'segment',
                    ],
                    [
                        'id'   => 'segment_' . $staticSegmentWithFilter->getId(),
                        'text' => $staticSegmentWithFilter->getName(),
                        'type' => 'segment',
                    ]
                ],
                'more' => false
            ],
            $this->manager->getSegmentByEntityName(WorkflowAwareEntity::class, null)
        );
    }

    public function testFindById()
    {
        /** @var Segment $segment */
        $segment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        $this->assertEquals($segment, $this->manager->findById($segment->getId()));
    }

    public function testGetEntityQueryBuilder()
    {
        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        $this->assertCount(50, $this->manager->getEntityQueryBuilder($dynamicSegment)->getQuery()->getResult());
    }

    public function testGetEntityQueryBuilderWithSorting()
    {
        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC_WITH_FILTER);
        $this->assertCount(0, $this->manager->getEntityQueryBuilder($dynamicSegment)->getQuery()->getResult());
    }

    public function testGetFilterSubQueryDynamic()
    {
        $registry = $this->getContainer()->get('doctrine');
        /** @var EntityRepository $repository */
        $repository = $registry->getRepository(WorkflowAwareEntity::class);

        $qb = $repository->createQueryBuilder('w');

        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        $result = $this->manager->getFilterSubQuery($dynamicSegment, $qb);
        $this->assertContains('FROM Oro\Bundle\TestFrameworkBundle\Entity\WorkflowAwareEntity', $result);
    }

    public function testGetFilterSubQueryDynamicWithLimit()
    {
        $registry = $this->getContainer()->get('doctrine');
        /** @var EntityRepository $repository */
        $repository = $registry->getRepository(WorkflowAwareEntity::class);

        $qb = $repository->createQueryBuilder('w');

        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC);
        $dynamicSegment->setRecordsLimit(10);
        $this->assertCount(10, $this->manager->getFilterSubQuery($dynamicSegment, $qb));
    }

    public function testGetFilterSubQueryStatic()
    {
        $registry = $this->getContainer()->get('doctrine');
        /** @var EntityRepository $repository */
        $repository = $registry->getRepository(WorkflowAwareEntity::class);

        $qb = $repository->createQueryBuilder('w');

        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_STATIC);
        $this->assertEquals(
            sprintf('SELECT snp.integerEntityId FROM %s snp WHERE snp.segment = :segment', SegmentSnapshot::class),
            $this->manager->getFilterSubQuery($dynamicSegment, $qb)
        );
    }

    public function testGetFilterSubQueryDynamicWithLimitAndNoResults()
    {
        $registry = $this->getContainer()->get('doctrine');
        /** @var EntityRepository $repository */
        $repository = $registry->getRepository(WorkflowAwareEntity::class);

        $qb = $repository->createQueryBuilder('w');

        /** @var Segment $dynamicSegment */
        $dynamicSegment = $this->getReference(LoadSegmentData::SEGMENT_DYNAMIC_WITH_FILTER);
        $dynamicSegment->setRecordsLimit(10);
        $result = $this->manager->getFilterSubQuery($dynamicSegment, $qb);
        $this->assertEquals([null], $result);
    }
}
