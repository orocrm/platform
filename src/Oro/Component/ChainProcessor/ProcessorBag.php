<?php

namespace Oro\Component\ChainProcessor;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ProcessorBag implements ProcessorBagInterface
{
    /** @var ProcessorFactoryInterface */
    protected $processorFactory;

    /** @var ProcessorIteratorFactoryInterface */
    protected $processorIteratorFactory;

    /** @var ProcessorApplicableCheckerFactoryInterface */
    protected $applicableCheckerFactory;

    /** @var bool */
    protected $debug;

    /**
     * @var array|null
     * after the bag is initialized this property is set to NULL,
     * this switches the bag in "frozen" state and further modification of it is prohibited
     * please note that applicable checkers can be added at any moment, even if the bag is frozen
     */
    protected $initialData = [];

    /** @var array */
    protected $additionalApplicableCheckers = [];

    /**
     * @var array
     *  [
     *      action => [
     *          [
     *              'processor'  => processorId,
     *              'attributes' => [key => value, ...]
     *          ],
     *          ...
     *      ],
     *      ...
     *  ]
     */
    protected $processors;

    /** @var ChainApplicableChecker */
    protected $processorApplicableChecker;

    /**
     * @param ProcessorFactoryInterface                       $processorFactory
     * @param bool                                            $debug
     * @param ProcessorApplicableCheckerFactoryInterface|null $applicableCheckerFactory
     * @param ProcessorIteratorFactoryInterface|null          $processorIteratorFactory
     */
    public function __construct(
        ProcessorFactoryInterface $processorFactory,
        $debug = false,
        ProcessorApplicableCheckerFactoryInterface $applicableCheckerFactory = null,
        ProcessorIteratorFactoryInterface $processorIteratorFactory = null
    ) {
        $this->processorFactory = $processorFactory;
        $this->debug = $debug;
        $this->applicableCheckerFactory = $applicableCheckerFactory ?: new ProcessorApplicableCheckerFactory();
        $this->processorIteratorFactory = $processorIteratorFactory ?: new ProcessorIteratorFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function addGroup($group, $action, $priority = 0)
    {
        $this->assertNotFrozen();

        $this->initialData['groups'][$action][$group] = $priority;
    }

    /**
     * {@inheritdoc}
     */
    public function addProcessor($processorId, array $attributes, $action = null, $group = null, $priority = 0)
    {
        $this->assertNotFrozen();

        if (null === $action) {
            $action = '';
        }
        if (!empty($group)) {
            $attributes['group'] = $group;
        }

        $this->initialData['processors'][$action][$priority][] = [
            'processor'  => $processorId,
            'attributes' => $attributes
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function addApplicableChecker(ApplicableCheckerInterface $checker, $priority = 0)
    {
        $this->additionalApplicableCheckers[$priority][] = $checker;
        $this->processorApplicableChecker = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessors(ContextInterface $context)
    {
        $this->ensureInitialized();
        $this->ensureProcessorApplicableCheckerInitialized();

        $action = $context->getAction();

        return $this->processorIteratorFactory->createProcessorIterator(
            isset($this->processors[$action]) ? $this->processors[$action] : [],
            $context,
            $this->processorApplicableChecker,
            $this->processorFactory
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getActions()
    {
        $this->ensureInitialized();

        return array_keys($this->processors);
    }

    /**
     * {@inheritdoc}
     */
    public function getActionGroups($action)
    {
        $this->ensureInitialized();

        $result = [];
        if (isset($this->processors[$action])) {
            foreach ($this->processors[$action] as $processor) {
                if (!isset($processor['attributes']['group'])) {
                    continue;
                }
                $group = $processor['attributes']['group'];
                if (!in_array($group, $result, true)) {
                    $result[] = $group;
                }
            }
        }

        return $result;
    }

    /**
     * Checks whether the processor bag can be modified
     */
    protected function assertNotFrozen()
    {
        if (null === $this->initialData) {
            throw new \RuntimeException('The ProcessorBag is frozen.');
        }
    }

    /**
     * Makes sure that the processor bag is initialized
     */
    protected function ensureInitialized()
    {
        if (null !== $this->initialData) {
            $this->initializeProcessors();
            $this->initialData = null;
        }
    }

    /**
     * Makes sure that the processor applicable checker is initialized
     */
    protected function ensureProcessorApplicableCheckerInitialized()
    {
        if (null === $this->processorApplicableChecker) {
            $this->initializeProcessorApplicableChecker();
        }
    }

    /**
     * Initializes $this->processors
     */
    protected function initializeProcessors()
    {
        $this->processors = [];

        if (!empty($this->initialData['processors'])) {
            $groups = !empty($this->initialData['groups'])
                ? $this->initialData['groups']
                : [];

            $startCommonProcessors = [];
            $endCommonProcessors   = [];
            if (!empty($this->initialData['processors'][''])) {
                foreach ($this->initialData['processors'][''] as $priority => $priorityData) {
                    foreach ($priorityData as $processor) {
                        if ($priority < 0) {
                            $endCommonProcessors[$priority][] = $processor;
                        } else {
                            $startCommonProcessors[$priority][] = $processor;
                        }
                    }
                }
                $startCommonProcessors = $this->sortByPriorityAndFlatten($startCommonProcessors);
                $endCommonProcessors   = $this->sortByPriorityAndFlatten($endCommonProcessors);
                unset($this->initialData['processors']['']);
            }

            foreach ($this->initialData['processors'] as $action => $actionData) {
                $this->processors[$action] = array_merge(
                    $startCommonProcessors,
                    $this->getSortedProcessors($action, $actionData, $groups),
                    $endCommonProcessors
                );
            }
        }
    }

    /**
     * @param string $action
     * @param array  $actionData
     * @param array  $groups
     *
     * @return array
     */
    protected function getSortedProcessors($action, $actionData, $groups)
    {
        $processors = [];
        foreach ($actionData as $priority => $priorityData) {
            foreach ($priorityData as $processor) {
                if (isset($processor['attributes']['group'])) {
                    $group = $processor['attributes']['group'];
                    if (!isset($groups[$action][$group])) {
                        throw new \RuntimeException(
                            sprintf(
                                'The group "%s" is not defined. Processor: "%s".',
                                $group,
                                $processor['processor']
                            )
                        );
                    }
                    $processorPriority = $this->calculatePriority($priority, $groups[$action][$group]);
                } else {
                    $processorPriority = $this->calculatePriority($priority);
                }
                $processors[$processorPriority][] = $processor;
            }
        }
        $processors = $this->sortByPriorityAndFlatten($processors);

        return $processors;
    }

    /**
     * Initializes $this->processorApplicableChecker
     */
    protected function initializeProcessorApplicableChecker()
    {
        $this->processorApplicableChecker = $this->applicableCheckerFactory->createApplicableChecker();
        if (!empty($this->additionalApplicableCheckers)) {
            $checkers = $this->sortByPriorityAndFlatten($this->additionalApplicableCheckers);
            foreach ($checkers as $checker) {
                $this->processorApplicableChecker->addChecker($checker);
            }
        }
        foreach ($this->processorApplicableChecker as $checker) {
            // add the "priority" attribute to the ignore list,
            // as it is added by LoadProcessorsCompilerPass to processors' attributes only in debug mode
            if ($this->debug && $checker instanceof MatchApplicableChecker) {
                $checker->addIgnoredAttribute('priority');
            }
            if ($checker instanceof ProcessorBagAwareApplicableCheckerInterface) {
                $checker->setProcessorBag($this);
            }
        }
    }

    /**
     * Calculates an internal priority of a processor based on its priority and a priority of its group.
     *
     * @param int      $processorPriority
     * @param int|null $groupPriority
     *
     * @return int
     */
    protected function calculatePriority($processorPriority, $groupPriority = null)
    {
        if (null === $groupPriority) {
            if ($processorPriority < 0) {
                $processorPriority += self::getIntervalPriority(-255, -255) + 1;
            } else {
                $processorPriority += self::getIntervalPriority(255, 255) + 2;
            }
        } else {
            if ($groupPriority < -255 || $groupPriority > 255) {
                throw new \RangeException(
                    sprintf(
                        'The value %d is not valid priority of a group. It must be between -255 and 255.',
                        $groupPriority
                    )
                );
            }
            if ($processorPriority < -255 || $processorPriority > 255) {
                throw new \RangeException(
                    sprintf(
                        'The value %d is not valid priority of a processor. It must be between -255 and 255.',
                        $processorPriority
                    )
                );
            }
        }

        return self::getIntervalPriority($processorPriority, $groupPriority);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    protected function sortByPriorityAndFlatten(array $items)
    {
        if (empty($items)) {
            return [];
        }

        krsort($items);
        $items = call_user_func_array('array_merge', $items);

        return $items;
    }

    /**
     * @param int $processorPriority
     * @param int $groupPriority
     *
     * @return int
     */
    private static function getIntervalPriority($processorPriority, $groupPriority)
    {
        return ($groupPriority * 511) + $processorPriority - 1;
    }
}
