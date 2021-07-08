<?php

namespace Oro\Component\Layout;

use Oro\Component\Layout\ExpressionLanguage\ExpressionProcessor;
use Oro\Component\Layout\Extension\ExtensionInterface;
use Oro\Component\Layout\Extension\PreloadedExtension;

class LayoutFactoryBuilder implements LayoutFactoryBuilderInterface
{
    /**
     * @var ExtensionInterface[]
     */
    private $extensions = [];

    /**
     * @var BlockTypeInterface[]
     *
     * Example:
     *  [
     *      'block_type_1' => BlockTypeInterface,
     *      'block_type_2' => BlockTypeInterface
     *  ]
     */
    private $types = [];

    /**
     * @var array of BlockTypeExtensionInterface[]
     *
     * Example:
     *  [
     *      'block_type_1' => array of BlockTypeExtensionInterface,
     *      'block_type_2' => array of BlockTypeExtensionInterface
     *  ]
     */
    private $typeExtensions = [];

    /**
     * @var array of LayoutUpdateInterface[]
     *
     * Example:
     *  [
     *      'item_1' => array of LayoutUpdateInterface,
     *      'item_2' => array of LayoutUpdateInterface
     *  ]
     */
    private $layoutUpdates = [];

    /**
     * @var LayoutRendererInterface[]
     *
     * Example:
     *  [
     *      'php'  => LayoutRendererInterface,
     *      'twig' => LayoutRendererInterface
     *  ]
     */
    private $renderers = [];

    /**
     * @var string
     */
    private $defaultRenderer;

    /**
     * @var ExpressionProcessor
     */
    private $expressionProcessor;

    /**
     * @var BlockViewCache|null
     */
    private $blockViewCache;

    /**
     * @var boolean
     */
    private $debug = false;

    /**
     * @param ExpressionProcessor $expressionProcessor
     * @param BlockViewCache|null $blockViewCache
     */
    public function __construct(ExpressionProcessor $expressionProcessor, BlockViewCache $blockViewCache = null)
    {
        $this->expressionProcessor = $expressionProcessor;
        $this->blockViewCache = $blockViewCache;
    }

    /**
     * @param boolean $debug
     *
     * @return LayoutFactoryBuilder
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addExtension(ExtensionInterface $extension)
    {
        $this->extensions[] = $extension;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addType(BlockTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addTypeExtension(BlockTypeExtensionInterface $typeExtension)
    {
        $this->typeExtensions[$typeExtension->getExtendedType()][] = $typeExtension;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addLayoutUpdate($id, LayoutUpdateInterface $layoutUpdate)
    {
        $this->layoutUpdates[$id][] = $layoutUpdate;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addRenderer($name, LayoutRendererInterface $renderer)
    {
        $this->renderers[$name] = $renderer;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultRenderer($name)
    {
        $this->defaultRenderer = $name;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLayoutFactory()
    {
        // initialize extension manager
        $registry = new LayoutRegistry();
        foreach ($this->extensions as $extension) {
            $registry->addExtension(clone $extension);
        }
        if (!empty($this->types) || !empty($this->typeExtensions) || !empty($this->layoutUpdates)) {
            $registry->addExtension(
                new PreloadedExtension(
                    $this->types,
                    $this->typeExtensions,
                    $this->layoutUpdates
                )
            );
        }

        // initialize renderer registry
        $rendererRegistry = new LayoutRendererRegistry();
        $defaultRenderer  = $this->defaultRenderer;
        foreach ($this->renderers as $name => $renderer) {
            $rendererRegistry->addRenderer($name, $renderer);
            if (!$defaultRenderer) {
                $defaultRenderer = $name;
            }
        }
        if ($defaultRenderer) {
            $rendererRegistry->setDefaultRenderer($defaultRenderer);
        }

        return new LayoutFactory($registry, $rendererRegistry, $this->expressionProcessor, $this->getBlockViewCache());
    }

    /**
     * @return BlockViewCache|null
     */
    private function getBlockViewCache()
    {
        return $this->debug === false ? $this->blockViewCache : null;
    }
}
