<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Entity\Mapping\AbstractEntityAttributeOption;

/**
 * Attribute options
 *
 * @ORM\Table(name="oro_flexibleentity_attribute_option")
 * @ORM\Entity(repositoryClass="Oro\Bundle\FlexibleEntityBundle\Entity\Repository\AttributeOptionRepository")
 */
class AttributeOption extends AbstractEntityAttributeOption
{

    /**
     * Overrided to change target entity name
     *
     * @var Attribute $attribute
     *
     * @ORM\ManyToOne(targetEntity="Attribute", inversedBy="options")
     */
    protected $attribute;

    /**
     * @var ArrayCollection $values
     *
     * @ORM\OneToMany(
     *     targetEntity="AttributeOptionValue", mappedBy="option", cascade={"persist", "remove"}, orphanRemoval=true
     * )
     */
    protected $optionValues;
}
