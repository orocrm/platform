<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\FlexibleEntityBundle\Entity\Mapping\AbstractEntityFlexibleValue;

/**
 * A concret flexible attribue value class
 *
 * @ORM\Entity()
 */
class FlexibleValue extends AbstractEntityFlexibleValue
{

    /**
     * @var Oro\Bundle\FlexibleEntityBundle\Entity\Attribute $attribute
     *
     * @ORM\ManyToOne(targetEntity="Oro\Bundle\FlexibleEntityBundle\Entity\Attribute")
     */
    protected $attribute;

    /**
     * @var Product $entity
     *
     * @ORM\ManyToOne(targetEntity="Flexible", inversedBy="values")
     */
    protected $entity;

    /**
     * Store options values
     *
     * @var ArrayCollection options
     *
     * @ORM\ManyToMany(targetEntity="Oro\Bundle\FlexibleEntityBundle\Entity\AttributeOption")
     */
    protected $options;

    /**
     * Store upload values
     *
     * @var Media $media
     *
     * @ORM\OneToOne(targetEntity="Oro\Bundle\FlexibleEntityBundle\Entity\Media", cascade="persist")
     * @ORM\JoinColumn(name="media_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $media;

    /**
     * Store metric value
     *
     * @var Metric $metric
     *
     * @ORM\OneToOne(targetEntity="Oro\Bundle\FlexibleEntityBundle\Entity\Metric", cascade="persist")
     * @ORM\JoinColumn(name="metric_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $metric;

    /**
     * Store price value
     *
     * @var Price $price
     *
     * @ORM\OneToOne(targetEntity="Oro\Bundle\FlexibleEntityBundle\Entity\Price", cascade="persist")
     * @ORM\JoinColumn(name="price_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $price;

    /**
     * Get media
     *
     * @return \Oro\Bundle\FlexibleEntityBundle\Entity\Media
     */
    public function getMedia()
    {
        return $this->media;
    }

    /**
     * Set media
     *
     * @param \Oro\Bundle\FlexibleEntityBundle\Entity\Media $media
     *
     * @return \Oro\Bundle\FlexibleEntityBundle\Entity\ProductValue
     */
    public function setMedia($media)
    {
        $this->media = $media;

        return $this;
    }

    /**
     * Get metric
     *
     * @return Metric
     */
    public function getMetric()
    {
        return $this->metric;
    }

    /**
     * Set metric
     *
     * @param Metric $metric
     *
     * @return AbstractEntityFlexibleValue
     */
    public function setMetric($metric)
    {
        $this->metric = $metric;

        return $this;
    }

    /**
     * Get price
     *
     * @return Price
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set price
     *
     * @param Price $price
     *
     * @return AbstractEntityFlexibleValue
     */
    public function setPrice($price)
    {
        $this->price = $price;

        return $this;
    }
}
