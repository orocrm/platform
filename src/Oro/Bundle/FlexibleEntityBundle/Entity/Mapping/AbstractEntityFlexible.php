<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractFlexible;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractFlexibleValue;
use Oro\Bundle\FlexibleEntityBundle\Model\FlexibleValueInterface;

/**
 * Base Doctrine ORM entity
 */
abstract class AbstractEntityFlexible extends AbstractFlexible
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var datetime $created
     *
     * @ORM\Column(type="datetime")
     */
    protected $created;

    /**
     * @var datetime $updated
     *
     * @ORM\Column(type="datetime")
     */
    protected $updated;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="AbstractEntityFlexibleValue", mappedBy="entity", cascade={"persist", "remove"})
     */
    protected $values;

    /**
     * Associative array of defined attributes
     *
     * @var array
     */
    protected $allAttributes;

    /**
     * Value class used to create new value
     *
     * @var string
     */
    protected $valueClass;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->allAttributes = array();
        $this->values        = new ArrayCollection();
    }

    /**
     * Get attributes
     *
     * @return array
     */
    public function getAllAttributes()
    {
        return $this->allAttributes;
    }

    /**
     * Set attributes
     *
     * @param array $attributes
     *
     * @return AbstractEntityFlexible
     */
    public function setAllAttributes($attributes)
    {
        $this->allAttributes = $attributes;

        return $this;
    }

    /**
     * Set value class
     *
     * @param string $valueClass
     *
     * @return AbstractEntityFlexible
     */
    public function setValueClass($valueClass)
    {
        $this->valueClass = $valueClass;

        return $this;
    }

    /**
     * Add value, override to deal with relation owner side
     *
     * @param FlexibleValueInterface $value
     *
     * @return AbstractEntityFlexible
     */
    public function addValue(FlexibleValueInterface $value)
    {
        $this->values[] = $value;
        $value->setEntity($this);

        return $this;
    }

    /**
     * Remove value
     *
     * @param FlexibleValueInterface $value
     */
    public function removeValue(FlexibleValueInterface $value)
    {
        $this->values->removeElement($value);
    }

    /**
     * Get values
     *
     * @return \ArrayAccess
     */
    public function getValues()
    {
        if (!isset($this->values) || !$this->values->count()) {
            return $this->values;
        }

        $collection = new ArrayCollection();
        foreach ($this->values as $value) {
            $collection[$value->getAttribute()->getCode()] = $value;
        }

        return $collection;
    }

    /**
     * Get value related to attribute code
     *
     * @param string $attributeCode
     * @param string $localeCode
     * @param string $scopeCode
     *
     * @return FlexibleValueInterface
     */
    public function getValue($attributeCode, $localeCode = null, $scopeCode = null)
    {
        $locale = ($localeCode) ? $localeCode : $this->getLocale();
        $scope  = ($scopeCode) ? $scopeCode : $this->getScope();

        $values = $this->filterValues($attributeCode, $locale, $scope);
        $value = (count($values) == 1) ? $values->first() : false;

        return $value;
    }

    /**
     * Filter product value per attribute code
     *
     * @param string $attribute
     * @param string $locale
     * @param string $scope
     */
    protected function filterValues($attribute, $locale, $scope)
    {
        $values = $this->getValues();

        if (empty($values)) {
            return array();
        }

        $values = $values->filter(
            function ($value) use ($attribute, $locale, $scope) {
                return $value->isMatching($attribute, $locale, $scope);
            }
        );

        return $values;
    }

    /**
     * Create a new value
     *
     * @param string $attributeCode
     * @param string $locale
     * @param string $scope
     *
     * @throws \Exception
     *
     * @return AbstractFlexibleValue
     */
    public function createValue($attributeCode, $locale = null, $scope = null)
    {
        if (!isset($this->allAttributes[$attributeCode])) {
            throw new \Exception(sprintf('Could not find attribute "%s".', $attributeCode));
        }

        $attribute = $this->allAttributes[$attributeCode];
        $value = new $this->valueClass();
        $value->setAttribute($attribute);
        if ($attribute->getTranslatable()) {
            $value->setLocale($locale);
        }
        if ($attribute->getScopable()) {
            $value->setScope($scope);
        }

        return $value;
    }

    /**
     * Check if a field or attribute exists
     *
     * @param string $name
     *
     * @return boolean
     */
    public function __isset($name)
    {
        // to authorize call to dynamic __get by twig, should be filter on existing attributes
        // cf http://twig.sensiolabs.org/doc/recipes.html#using-dynamic-object-properties
        $values = $this->getValues();

        if (empty($values)) {
            return false;
        }

        $values = $values->filter(
            function ($value) use ($name) {
                if ($value->getAttribute()->getCode() == $name) {
                    return true;
                }
            }
        );

        return (count($values) >= 1);
    }

    /**
     * Add support of magic method getAttributeCode, setAttributeCode, addAttributeCode
     *
     * @param string $method
     * @param string $arguments
     *
     * @throws \Exception
     *
     * @return Ambigous <mixed, multitype:>
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function __call($method, $arguments)
    {
        if (preg_match('/get(.*)/', $method, $matches)) {
            $attributeCode = Inflector::tableize($matches[1]);

            return $this->getValue($attributeCode);
        }

        $attributeCode = null;
        if (preg_match('/set(.*)/', $method, $matches)) {
            $attributeCode = Inflector::tableize($matches[1]);
            $method        = 'setData';
        } elseif (preg_match('/add(.*)/', $method, $matches)) {
            $attributeCode = Inflector::tableize($matches[1]);
            $method        = 'addData';
        }

        if ($attributeCode !== null) {
            $data   = $arguments[0];
            $locale = (isset($arguments[1])) ? $arguments[1] : $this->getLocale();
            $scope  = (isset($arguments[2])) ? $arguments[2] : $this->getScope();
            $value  = $this->getValue($attributeCode, $locale, $scope);
            if ($value === false) {
                $value = $this->createValue($attributeCode, $locale, $scope);
                $this->addValue($value);
            }
            $value->$method($data);

            return $this;
        }
    }

    /**
     * Get value data by attribute code
     *
     * @param string $attCode
     *
     * @return mixed
     */
    public function __get($attCode)
    {
        $methodName = "get{$attCode}";
        if (method_exists($this, $methodName)) {
            return $this->$methodName();

        } else {
            // dynamic call to get value data
            return $this->getValue($attCode);
        }
    }
}
