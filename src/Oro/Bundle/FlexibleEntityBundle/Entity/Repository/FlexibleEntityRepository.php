<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Oro\Bundle\FlexibleEntityBundle\AttributeType\AbstractAttributeType;
use Oro\Bundle\FlexibleEntityBundle\Doctrine\ORM\FlexibleQueryBuilder;
use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;
use Oro\Bundle\FlexibleEntityBundle\Exception\UnknownAttributeException;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractFlexible;
use Oro\Bundle\FlexibleEntityBundle\Model\Behavior\ScopableInterface;
use Oro\Bundle\FlexibleEntityBundle\Model\Behavior\TranslatableInterface;

/**
 * Base repository for flexible entity
 *
 *
 */
class FlexibleEntityRepository extends EntityRepository implements TranslatableInterface, ScopableInterface
{
    /**
     * Flexible entity config
     * @var array
     */
    protected $flexibleConfig;

    /**
     * Locale code
     * @var string
     */
    protected $locale;

    /**
     * Scope code
     * @var string
     */
    protected $scope;

    /**
     * Get flexible entity config
     *
     * @return array $config
     */
    public function getFlexibleConfig()
    {
        return $this->flexibleConfig;
    }

    /**
     * Set flexible entity config

     * @param array $config
     *
     * @return FlexibleEntityRepository
     */
    public function setFlexibleConfig($config)
    {
        $this->flexibleConfig = $config;

        return $this;
    }

    /**
     * Return asked locale code or default one
     *
     * @return string
     */
    public function getLocale()
    {
        if (!$this->locale) {
            $this->locale = $this->flexibleConfig['default_locale'];
        }

        return $this->locale;
    }

    /**
     * Set locale code
     *
     * @param string $code
     *
     * @return FlexibleEntityRepository
     */
    public function setLocale($code)
    {
        $this->locale = $code;

        return $this;
    }

    /**
     * Return asked scope code or default one
     *
     * @return string
     */
    public function getScope()
    {
        if (!$this->scope) {
            $this->scope = $this->flexibleConfig['default_scope'];
        }

        return $this->scope;
    }

    /**
     * Set scope code
     *
     * @param string $code
     *
     * @return FlexibleEntityRepository
     */
    public function setScope($code)
    {
        $this->scope = $code;

        return $this;
    }

    /**
     * Finds attributes
     *
     * @param array $attributeCodes attribute codes
     *
     * @throws UnknownAttributeException
     *
     * @return array The objects.
     */
    public function getCodeToAttributes(array $attributeCodes)
    {
        // prepare entity attributes query
        $attributeAlias = 'Attribute';
        $attributeName = $this->flexibleConfig['attribute_class'];
        $attributeRepo = $this->_em->getRepository($attributeName);
        $qb = $attributeRepo->createQueryBuilder($attributeAlias);
        $qb->andWhere('Attribute.entityType = :type')->setParameter('type', $this->_entityName);

        // filter by code
        if (!empty($attributeCodes)) {
            $qb->andWhere($qb->expr()->in('Attribute.code', $attributeCodes));
        }

        // prepare associative array
        $attributes = $qb->getQuery()->getResult();
        $codeToAttribute = array();
        foreach ($attributes as $attribute) {
            $codeToAttribute[$attribute->getCode()]= $attribute;
        }

        // raise exception
        if (!empty($attributeCodes) and count($attributeCodes) != count($codeToAttribute)) {
            $missings = array_diff($attributeCodes, array_keys($codeToAttribute));
            throw new UnknownAttributeException(
                'Attribute(s) with code '.implode(', ', $missings).' not exists for entity '.$this->_entityName
            );
        }

        return $codeToAttribute;
    }

    /**
     * @param QueryBuilder $qb
     * @param string       $locale
     * @param string       $scope
     */
    public function getFlexibleQueryBuilder($qb)
    {
        return new FlexibleQueryBuilder($qb, $this->getLocale(), $this->getScope());
    }

    /**
     * Find flexible attribute by code
     *
     * @param string $code
     *
     * @throws UnknownAttributeException
     *
     * @return AbstractEntityAttribute
     */
    public function findAttributeByCode($code)
    {
        $attributeName = $this->flexibleConfig['attribute_class'];
        $attributeRepo = $this->_em->getRepository($attributeName);
        $attribute = $attributeRepo->findOneBy(array('entityType' => $this->_entityName, 'code' => $code));

        return $attribute;
    }

    /**
     * Add join to values tables
     *
     * @param QueryBuilder $qb
     */
    protected function addJoinToValueTables(QueryBuilder $qb)
    {
        $qb->leftJoin(current($qb->getRootAliases()).'.values', 'Value')
            ->leftJoin('Value.attribute', 'Attribute')
            ->leftJoin('Value.options', 'ValueOption')
            ->leftJoin('ValueOption.optionValues', 'AttributeOptionValue');
    }

    /**
     * Finds entities and attributes values by a set of criteria, same coverage than findBy
     *
     * @param array      $attributes attribute codes
     * @param array      $criteria   criterias
     * @param array|null $orderBy    order by
     * @param int|null   $limit      limit
     * @param int|null   $offset     offset
     *
     * @return array The objects.
     */
    public function findByWithAttributesQB(array $attributes = array(), array $criteria = null, array $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder('Entity');
        $this->addJoinToValueTables($qb);
        $codeToAttribute = $this->getCodeToAttributes($attributes);
        $attributes = array_keys($codeToAttribute);

        if (!is_null($criteria)) {
            foreach ($criteria as $attCode => $attValue) {
                $this->applyFilterByAttribute($qb, $attCode, $attValue);
            }
        }
        if (!is_null($orderBy)) {
            foreach ($orderBy as $attCode => $direction) {
                $this->applySorterByAttribute($qb, $attCode, $direction);
            }
        }

        // use doctrine paginator to avoid count problem with left join of values
        if (!is_null($offset) and !is_null($limit)) {
            $qb->setFirstResult($offset)->setMaxResults($limit);
            $paginator = new Paginator($qb->getQuery(), $fetchJoinCollection = true);

            return $paginator;
        }

        return $qb;
    }

    /**
     * Finds entities and attributes values by a set of criteria, same coverage than findBy
     *
     * @param array      $attributes attribute codes
     * @param array      $criteria   criterias
     * @param array|null $orderBy    order by
     * @param int|null   $limit      limit
     * @param int|null   $offset     offset
     *
     * @return array The objects.
     */
    public function findByWithAttributes(array $attributes = array(), array $criteria = null, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this
            ->findByWithAttributesQB($attributes, $criteria, $orderBy, $limit, $offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Apply a filter by attribute value
     *
     * @param QueryBuilder $qb             query builder to update
     * @param string       $attributeCode  attribute code
     * @param string|array $attributeValue value(s) used to filter
     * @param string       $operator       operator to use
     */
    public function applyFilterByAttribute(QueryBuilder $qb, $attributeCode, $attributeValue, $operator = '=')
    {
        $codeToAttribute = $this->getCodeToAttributes(array());
        $attributeCodes = array_keys($codeToAttribute);
        if (in_array($attributeCode, $attributeCodes)) {
            $attribute = $codeToAttribute[$attributeCode];
            $this->getFlexibleQueryBuilder($qb)->addAttributeFilter($attribute, $operator, $attributeValue);

        } else {
            $field = current($qb->getRootAliases()).'.'.$attributeCode;
            $qb->andWhere($this->getFlexibleQueryBuilder($qb)->prepareCriteriaCondition($field, $operator, $attributeValue));
        }
    }

    /**
     * Apply a sort by attribute value
     *
     * @param QueryBuilder $qb            query builder to update
     * @param string       $attributeCode attribute code
     * @param string       $direction     direction to use
     */
    public function applySorterByAttribute(QueryBuilder $qb, $attributeCode, $direction)
    {
        $codeToAttribute = $this->getCodeToAttributes(array());
        $attributeCodes = array_keys($codeToAttribute);
        if (in_array($attributeCode, $attributeCodes)) {
            $attribute = $codeToAttribute[$attributeCode];
            $this->getFlexibleQueryBuilder($qb)->addAttributeOrderBy($attribute, $direction);
        } else {
            $qb->addOrderBy(current($qb->getRootAliases()).'.'.$attributeCode, $direction);
        }
    }

    /**
     * Find entity with attributes values
     *
     * @param int $id entity id
     *
     * @return Entity the entity
     */
    public function findWithAttributes($id)
    {
        $flexibles = $this->findByWithAttributes(array(), array('id' => $id));

        return count($flexibles) ? current($flexibles) : null;
    }

    /**
     * Load a flexible entity with its attributes sorted by sortOrder
     *
     * @param integer $id
     *
     * @return AbstractFlexible|null
     * @throws NonUniqueResultException
     */
    public function findWithSortedAttribute($id)
    {
        return $this
            ->findByWithAttributesQB(array(), array('id' => $id))
            ->addOrderBy('Attribute.sortOrder')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
