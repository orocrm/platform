<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Extend\RelationType as RelationTypeBase;

class FieldType extends AbstractType
{
    const ORIGINAL_FIELD_NAMES_ATTRIBUTE = 'original_field_names';
    const TYPE_LABEL_PREFIX              = 'oro.entity_extend.form.data_type.';
    const GROUP_TYPE_PREFIX              = 'oro.entity_extend.form.data_type_group.';
    const GROUP_FIELDS                   = 'fields';
    const GROUP_RELATIONS                = 'relations';

    /**
     * @var array
     */
    protected $types = [
        self::GROUP_FIELDS       => [
            'string',
            'integer',
            'smallint',
            'bigint',
            'boolean',
            'decimal',
            'date',
            'datetime',
            'text',
            'float',
            'money',
            'percent',
            'file',
            'image',
            'optionSet',
            'enum',
            'multiEnum',
        ],
        self::GROUP_RELATIONS    => [
//            BAP-5875
//            RelationTypeBase::ONE_TO_ONE,
            RelationTypeBase::ONE_TO_MANY,
            RelationTypeBase::MANY_TO_ONE,
            RelationTypeBase::MANY_TO_MANY,
        ],
    ];

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var ExtendDbIdentifierNameGenerator
     */
    protected $nameGenerator;

    /**
     * @param ConfigManager                   $configManager
     * @param TranslatorInterface             $translator
     * @param ExtendDbIdentifierNameGenerator $nameGenerator
     */
    public function __construct(
        ConfigManager $configManager,
        TranslatorInterface $translator,
        ExtendDbIdentifierNameGenerator $nameGenerator
    ) {
        $this->configManager = $configManager;
        $this->translator    = $translator;
        $this->nameGenerator = $nameGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'fieldName',
            'text',
            [
                'label'       => 'oro.entity_extend.form.field_name.label',
                'block'       => 'general',
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => $this->nameGenerator->getMaxCustomEntityFieldNameSize()])
                ],
            ]
        );

        $originalFieldNames = [];
        $reverseRelationTypes = $this->getReverseRelationTypes($options['class_name'], $originalFieldNames);
        if (!empty($originalFieldNames)) {
            $builder->setAttribute(self::ORIGINAL_FIELD_NAMES_ATTRIBUTE, $originalFieldNames);
        }

        $builder->add(
            'type',
            'genemu_jqueryselect2_choice',
            [
                'choices'     => $this->getFieldTypeChoices($reverseRelationTypes),
                'empty_value' => '',
                'block'       => 'general',
                'configs'     => [
                    'placeholder'          => self::TYPE_LABEL_PREFIX . 'choose_value',
                ],
                'translatable_groups'  => false,
                'translatable_options' => false
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setRequired(['class_name'])
            ->setDefaults(
                [
                    'require_js'   => [],
                    'block_config' => [
                        'general' => [
                            'title'    => $this->translator->trans('oro.entity_config.block_titles.general.label'),
                            'priority' => 10,
                        ]
                    ]
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_entity_extend_field_type';
    }

    /**
     * @param array $reverseRelationTypes
     *
     * @return array
     */
    protected function getFieldTypeChoices($reverseRelationTypes)
    {
        $fieldTypes = $relationTypes = [];

        foreach ($this->types[self::GROUP_FIELDS] as $type) {
            $fieldTypes[$type] = $this->translator->trans(self::TYPE_LABEL_PREFIX . $type);
        }
        foreach ($this->types[self::GROUP_RELATIONS] as $type) {
            $relationTypes[$type] = $this->translator->trans(self::TYPE_LABEL_PREFIX . $type);
        }

        uasort($fieldTypes, 'strcasecmp');
        uasort($relationTypes, 'strcasecmp');

        if (!empty($reverseRelationTypes)) {
            uasort($reverseRelationTypes, 'strcasecmp');
            $relationTypes = array_merge($relationTypes, $reverseRelationTypes);
        }

        $result = [
            $this->translator->trans(self::GROUP_TYPE_PREFIX . self::GROUP_FIELDS)    => $fieldTypes,
            $this->translator->trans(self::GROUP_TYPE_PREFIX . self::GROUP_RELATIONS) => $relationTypes,
        ];

        return $result;
    }

    /**
     * @param string $className
     * @param array  $originalFieldNames
     * @return array
     */
    protected function getReverseRelationTypes($className, array &$originalFieldNames)
    {
        $extendProvider = $this->configManager->getProvider('extend');
        $entityConfig   = $extendProvider->getConfig($className);

        $entityProvider = $this->configManager->getProvider('entity');

        $result = [];
        if ($entityConfig->is('relation')) {
            $relations = $entityConfig->get('relation');
            foreach ($relations as $relationKey => $relation) {
                if (!$this->isAvailableRelation($extendProvider, $relation, $relationKey)) {
                    continue;
                }

                /** @var FieldConfigId $fieldId */
                $fieldId = $relation['field_id'];
                /** @var FieldConfigId $targetFieldId */
                $targetFieldId = $relation['target_field_id'];

                $entityLabel = $entityProvider->getConfig($targetFieldId->getClassName())->get('label');
                $fieldLabel  = $entityProvider->getConfigById($targetFieldId)->get('label');
                $fieldName   = $fieldId ? $fieldId->getFieldName() : '';

                $maxFieldNameLength = $this->nameGenerator->getMaxCustomEntityFieldNameSize();
                if (strlen($fieldName) > $maxFieldNameLength) {
                    $cutFieldName                      = substr($fieldName, 0, $maxFieldNameLength);
                    $originalFieldNames[$cutFieldName] = $fieldName;
                    $fieldName                         = $cutFieldName;
                }

                $key          = $relationKey . '||' . $fieldName;
                $result[$key] = $this->translator->trans(
                    self::TYPE_LABEL_PREFIX . 'inverse_relation',
                    [
                        '%entity_name%' => $this->translator->trans($entityLabel),
                        '%field_name%'  => $this->translator->trans($fieldLabel)
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * Check if reverse relation can be created
     *
     * @param ConfigProvider $extendProvider
     * @param array          $relation
     * @param string         $relationKey
     *
     * @return bool
     */
    protected function isAvailableRelation(
        ConfigProvider $extendProvider,
        array $relation,
        $relationKey
    ) {
        /** @var FieldConfigId|false $fieldId */
        $fieldId = $relation['field_id'];
        /** @var FieldConfigId $targetFieldId */
        $targetFieldId = $relation['target_field_id'];

        if (!$targetFieldId) {
            return false;
        }

        // additional check for reverse relation of manyToOne field type
        $targetEntityConfig = $extendProvider->getConfig($targetFieldId->getClassName());
        if (!$relation['assign']) {
            if (false === (
                    !$relation['assign']
                    && !$fieldId
                    && $targetFieldId->getFieldType() == RelationTypeBase::MANY_TO_ONE
                    && $targetEntityConfig->get('relation')
                    && $targetEntityConfig->get('relation')[$relationKey]['assign']
                )
            ) {
                return false;
            }
        }

        // case when entity A (e.g. Contact) has one-to-many
        // (reverse many-to-one) relation
        // from entity B (e.g. ContactAddress) but that relation has no FieldModel by design
        if ($fieldId
                && false == $relation['owner']
                && $targetFieldId->getFieldType() == RelationTypeBase::MANY_TO_ONE
        ) {
            return false;
        }

        if ($fieldId
            && $extendProvider->hasConfigById($fieldId)
            && $extendProvider->getConfigById($fieldId)->is('state', ExtendScope::STATE_DELETE)
        ) {
            return false;
        }

        return true;
    }
}
