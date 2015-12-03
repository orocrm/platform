<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity\Mapping;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOption;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOptionValue;

/**
 * Base Doctrine ORM entity attribute option value
 */
abstract class AbstractEntityAttributeOptionValue extends AbstractAttributeOptionValue
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
     * @var AbstractEntityAttributeOption $option
     *
     * @ORM\ManyToOne(targetEntity="AbstractEntityAttributeOption", inversedBy="optionValues")
     */
    protected $option;

    /**
     * Locale scope
     * @var string $locale
     *
     * @ORM\Column(name="locale_code", type="string", length=20, nullable=true)
     */
    protected $locale;

    /**
     * @var string $value
     *
     * @ORM\Column(name="value", type="string", length=255)
     */
    protected $value;
}
