<?php

namespace Oro\Bundle\FlexibleEntityBundle\Entity\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttribute;
use Oro\Bundle\FlexibleEntityBundle\Model\AbstractAttributeOption;

/**
 * Base Doctrine ORM entity attribute
 */
abstract class AbstractEntityAttribute extends AbstractAttribute
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
     * @var string $code
     *
     * @ORM\Column(name="code", type="string", length=255)
     */
    protected $code;

    /**
     * @var string $entityType
     *
     * @ORM\Column(name="entity_type", type="string", length=255)
     */
    protected $entityType;

    /**
     * @var string $attributeType
     *
     * @ORM\Column(name="attribute_type", type="string", length=255)
     */
    protected $attributeType;

    /**
     * @var string $backendType
     *
     * @ORM\Column(name="backend_type", type="string", length=255)
     */
    protected $backendType;

    /**
     * @var string $backendStorage
     *
     * @ORM\Column(name="backend_storage", type="string", length=255)
     */
    protected $backendStorage;

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
     * @ORM\Column(name="is_required", type="boolean")
     */
    protected $required;

    /**
     * @ORM\Column(name="is_unique", type="boolean")
     */
    protected $unique;

    /**
     * @ORM\Column(name="default_value", type="text", length=65532, nullable=true)
     */
    protected $defaultValue;

    /**
     * @ORM\Column(name="is_searchable", type="boolean")
     */
    protected $searchable;

    /**
     * @ORM\Column(name="is_translatable", type="boolean")
     */
    protected $translatable;

    /**
     * @ORM\Column(name="is_scopable", type="boolean")
     */
    protected $scopable;

    /**
     * @var ArrayCollection $options
     *
     * @ORM\OneToMany(
     *     targetEntity="AbstractEntityAttributeOption",
     *     mappedBy="attribute",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    protected $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options      = new ArrayCollection();
        $this->required     = false;
        $this->unique       = false;
        $this->defaultValue = null;
        $this->searchable   = false;
        $this->translatable = false;
        $this->scopable     = false;
    }

    /**
     * Add option (we do set attribute to deal with natural doctrine owner side and cascade)
     *
     * @param AbstractAttributeOption $option
     *
     * @return AbstractAttribute
     */
    public function addOption(AbstractAttributeOption $option)
    {
        $this->options[] = $option;
        $option->setAttribute($this);

        return $this;
    }
}
