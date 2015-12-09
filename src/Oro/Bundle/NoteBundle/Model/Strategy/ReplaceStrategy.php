<?php

namespace Oro\Bundle\NoteBundle\Model\Strategy;

use Oro\Bundle\ActivityListBundle\Entity\ActivityList;
use Oro\Bundle\ActivityListBundle\Entity\Manager\ActivityListManager;
use Symfony\Component\Security\Core\Util\ClassUtils;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityMergeBundle\Data\FieldData;
use Oro\Bundle\EntityMergeBundle\Model\Strategy\StrategyInterface;
use Oro\Bundle\NoteBundle\Model\MergeModes;

/**
 * Class ReplaceStrategy
 * @package Oro\Bundle\NoteBundle\Model\Strategy
 */
class ReplaceStrategy implements StrategyInterface
{
    /** @var DoctrineHelper  */
    protected $doctrineHelper;

    /** @var ActivityListManager  */
    protected $activityListManager;

    /**
     * @param DoctrineHelper $doctrineHelper
     */
    public function __construct(DoctrineHelper $doctrineHelper, ActivityListManager $activityListManager)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->activityListManager = $activityListManager;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(FieldData $fieldData)
    {
        $entityData    = $fieldData->getEntityData();
        $masterEntity  = $entityData->getMasterEntity();
        $sourceEntity  = $fieldData->getSourceEntity();

        $queryBuilder = $this->doctrineHelper->getEntityRepository('OroNoteBundle:Note')
            ->getBaseAssociatedNotesQB(ClassUtils::getRealClass($masterEntity), $masterEntity->getId());
        $notes = $queryBuilder->getQuery()->getResult();

        if (!empty($notes)) {
            $entityManager = $this->doctrineHelper->getEntityManager(current($notes));
            foreach ($notes as $note) {
                $entityManager->remove($note);
            }
        }

        $fieldMetadata = $fieldData->getMetadata();
        $activityClass = $fieldMetadata->get('type');
        $entityClass = ClassUtils::getRealClass($sourceEntity);
        $queryBuilder = $this->doctrineHelper
            ->getEntityRepository(ActivityList::ENTITY_NAME)
            ->getActivityListQueryBuilderByActivityClass($entityClass, $sourceEntity->getId(), $activityClass);
        $activityListItems = $queryBuilder->getQuery()->getResult();

        $activityIds = array_column($activityListItems, 'id');
        $this->activityListManager
            ->replaceActivityTargetWithPlainQuery(
                $activityIds,
                $entityClass,
                $sourceEntity->getId(),
                $masterEntity->getId()
            );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(FieldData $fieldData)
    {
        return $fieldData->getMode() === MergeModes::NOTES_REPLACE;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'notes_replace';
    }
}
