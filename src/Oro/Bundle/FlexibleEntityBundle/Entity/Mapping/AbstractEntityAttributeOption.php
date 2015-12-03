<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOption;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOptionValue;

/**
 * Base Doctrine ORM entity attribute option
 */
abstract class AbstractEntityAttributeOption extends AbstractAttributeOption
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
     * @var Attribute $attribute
     *
     * @ORM\ManyToOne(targetEntity="AbstractEntityAttribute")
     */
    protected $attribute;

    /**
     * @ORM\Column(name="is_translatable", type="boolean")
     */
    protected $translatable;

    /**
     * @ORM\Column(name="sort_order", type="integer")
     */
    protected $sortOrder;

    /**
     * @var ArrayCollection $optionValues
     *
     * @ORM\OneToMany(
     *     targetEntity="AbstractEntityAttributeOptionValue",
     *     mappedBy="option",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    protected $optionValues;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->optionValues = new ArrayCollection();
        $this->translatable = true;
        $this->sortOrder    = 1;
    }

    /**
     * Add option value
     *
     * @param AbstractAttributeOptionValue $value
     *
     * @return AbstractAttribute
     */
    public function addOptionValue(AbstractAttributeOptionValue $value)
    {
        $this->optionValues[] = $value;
        $value->setOption($this);

        return $this;
    }

    /**
     * Remove value
     *
     * @param AbstractAttributeOptionValue $value
     *
     * @return AbstractAttributeOption
     */
    public function removeOptionValue(AbstractAttributeOptionValue $value)
    {
        $this->optionValues->removeElement($value);

        return $this;
    }

    /**
     * Get localized value
     *
     * @return AbstractEntityAttributeOptionValue
     */
    public function getOptionValue()
    {
        $translatable = $this->translatable;
        $locale = $this->getLocale();
        $values = $this->getOptionValues()->filter(
            function ($value) use ($translatable, $locale) {
                // return relevant translated value
                if ($translatable and $value->getLocale() == $locale) {
                    return true;
                } elseif (!$translatable) {
                    return true;
                }
            }
        );
        $value = $values->first();

        return $value;
    }

    /**
     * To string
     *
     * @return string
     */
    public function __toString()
    {
        $value = $this->getOptionValue();

        return ($value) ? (string) $value->getValue() : '';
    }
}
