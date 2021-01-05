<?php

namespace Oro\Bundle\QueryDesignerBundle\QueryDesigner;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\EntityBundle\Provider\VirtualFieldProviderInterface;
use Oro\Bundle\EntityBundle\Provider\VirtualRelationProviderInterface;

/**
 * Provides a base functionality to convert a query definition created by the query designer to an ORM query.
 */
abstract class AbstractOrmQueryConverter extends AbstractQueryConverter
{
    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var ClassMetadataInfo[] */
    protected $classMetadataLocalCache;

    /**
     * @param FunctionProviderInterface        $functionProvider
     * @param VirtualFieldProviderInterface    $virtualFieldProvider
     * @param VirtualRelationProviderInterface $virtualRelationProvider
     * @param ManagerRegistry                  $doctrine
     */
    public function __construct(
        FunctionProviderInterface $functionProvider,
        VirtualFieldProviderInterface $virtualFieldProvider,
        VirtualRelationProviderInterface $virtualRelationProvider,
        ManagerRegistry $doctrine
    ) {
        parent::__construct($functionProvider, $virtualFieldProvider, $virtualRelationProvider);
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    protected function getJoinType($joinId)
    {
        $joinType = parent::getJoinType($joinId);

        if ($joinType === null) {
            $entityClassName = $this->getEntityClassName($joinId);
            if (!empty($entityClassName)) {
                $fieldName = $this->getFieldName($joinId);
                $metadata = $this->getClassMetadata($entityClassName);
                $associationMapping = $metadata->getAssociationMapping($fieldName);
                $nullable = true;
                if (isset($associationMapping['joinColumns'])) {
                    $nullable = false;
                    foreach ($associationMapping['joinColumns'] as $joinColumn) {
                        $nullable = ($nullable || ($joinColumn['nullable'] ?? false));
                    }
                }
                $joinType = $nullable ? self::LEFT_JOIN : self::INNER_JOIN;
            }
        }

        return $joinType;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldType($className, $fieldName)
    {
        $result = parent::getFieldType($className, $fieldName);
        if (null === $result) {
            $result = $this->getClassMetadata($className)->getTypeOfField($fieldName);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getUnidirectionalJoinCondition($joinTableAlias, $joinFieldName, $joinAlias, $entityClassName)
    {
        $metaData = $this->getClassMetadata($entityClassName);

        // In the case of virtual fields, metadata may not have an association mapping
        if ($metaData->hasAssociation($joinFieldName)) {
            $associationMapping = $metaData->getAssociationMapping($joinFieldName);
            if ($associationMapping['type'] & ClassMetadataInfo::TO_MANY) {
                return sprintf('%s MEMBER OF %s.%s', $joinTableAlias, $joinAlias, $joinFieldName);
            }
        }

        return sprintf('%s.%s = %s', $joinAlias, $joinFieldName, $joinTableAlias);
    }

    /**
     * Returns a metadata for the given entity
     *
     * @param string $className
     *
     * @return ClassMetadataInfo
     */
    protected function getClassMetadata($className)
    {
        if (isset($this->classMetadataLocalCache[$className])) {
            return $this->classMetadataLocalCache[$className];
        }

        $classMetadata = $this->doctrine
            ->getManagerForClass($className)
            ->getClassMetadata($className);
        $this->classMetadataLocalCache[$className] = $classMetadata;

        return $classMetadata;
    }
}
