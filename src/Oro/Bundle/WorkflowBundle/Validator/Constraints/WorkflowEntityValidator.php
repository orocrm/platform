<?php

namespace Oro\Bundle\WorkflowBundle\Validator\Constraints;

use Doctrine\ORM\EntityManager;
use Oro\Bundle\EntityBundle\Helper\FieldHelper;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\FormBundle\Entity\EmptyItem;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use Oro\Bundle\WorkflowBundle\Model\WorkflowPermissionRegistry;
use Oro\Bundle\WorkflowBundle\Restriction\RestrictionManager;
use Oro\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class WorkflowEntityValidator extends ConstraintValidator
{
    /** @var EntityManager */
    protected $entityManager;

    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var WorkflowPermissionRegistry */
    protected $permissionRegistry;

    /** @var RestrictionManager */
    protected $restrictionManager;

    /** @var PropertyAccessor */
    protected $propertyAccessor;

    /** @var FieldHelper */
    protected $fieldHelper;

    /**
     * @param EntityManager $entityManager
     * @param DoctrineHelper $doctrineHelper
     * @param WorkflowPermissionRegistry $permissionRegistry
     * @param RestrictionManager $restrictionManager
     * @param FieldHelper $fieldHelper
     */
    public function __construct(
        EntityManager $entityManager,
        DoctrineHelper $doctrineHelper,
        WorkflowPermissionRegistry $permissionRegistry,
        RestrictionManager $restrictionManager,
        FieldHelper $fieldHelper
    ) {
        $this->entityManager      = $entityManager;
        $this->doctrineHelper     = $doctrineHelper;
        $this->permissionRegistry = $permissionRegistry;
        $this->restrictionManager = $restrictionManager;
        $this->fieldHelper        = $fieldHelper;

        $this->propertyAccessor = new PropertyAccessor();
    }

    /**
     * {@inheritdoc}
     * @param WorkflowEntity $constraint
     */
    public function validate($value, Constraint $constraint)
    {
        if (!is_object($value)) {
            return;
        }

        // Skip changes for workflow transition form
        $root = $this->context->getRoot();
        if ($root instanceof Form && WorkflowTransitionType::NAME === $root->getName()) {
            return;
        }

        $class = $this->doctrineHelper->getEntityClass($value);
        $hasClassRestrictions = $this->restrictionManager->hasEntityClassRestrictions($class);
        $restrictions = [];
        if (!($this->permissionRegistry->supportsClass($class) || $hasClassRestrictions)) {
            return;
        }
        if ($hasClassRestrictions) {
            $restrictions = $this->restrictionManager->getEntityRestrictions($value);
        }

        if ($this->doctrineHelper->isNewEntity($value)) {
            $this->validateNewEntity($value, $constraint, $restrictions);
        } else {
            $this->validateExistingEntity($value, $constraint, $restrictions);
        }
    }

    /**
     * @param object         $object
     * @param WorkflowEntity $constraint
     * @param array          $restrictions
     */
    protected function validateNewEntity($object, WorkflowEntity $constraint, array $restrictions)
    {
        foreach ($restrictions as $restriction) {
            if ($restriction['mode'] === 'full') {
                $fieldValue = $this->propertyAccessor->getValue($object, $restriction['field']);
                if ($fieldValue === null || ($fieldValue instanceof EmptyItem && $fieldValue->isEmpty())) {
                    continue;
                }
                $this->addFieldViolation($restriction['field'], $constraint->createFieldMessage);
            } else {
                $this->validateAllowedValues($object, $constraint->createFieldMessage, $restriction);
            }
        }
    }

    /**
     * @param object         $object
     * @param WorkflowEntity $constraint
     * @param array          $restrictions
     */
    protected function validateExistingEntity($object, WorkflowEntity $constraint, array $restrictions)
    {
        $permissions = $this->permissionRegistry->getEntityPermissions($object);

        if (true === $permissions['UPDATE'] && empty($restrictions)) {
            return;
        }

        $changeSet = $this->getEntityChangeSet($object);
        if (empty($changeSet)) {
            return;
        }

        if ($permissions['UPDATE'] === false && $changeSet) {
            $this->context->addViolation($constraint->updateEntityMessage);
            return;
        }

        $restrictionsOnChangeSet = array_filter($restrictions, function ($restriction) use ($changeSet) {
            return isset($changeSet[$restriction['field']]);
        });

        $this->validateUpdatedFields($object, $constraint, $restrictionsOnChangeSet);
    }

    /**
     * @param object         $object
     * @param WorkflowEntity $constraint
     * @param array          $restrictionsOnChangeSet
     */
    protected function validateUpdatedFields($object, WorkflowEntity $constraint, array $restrictionsOnChangeSet)
    {
        foreach ($restrictionsOnChangeSet as $restriction) {
            if ($restriction['mode'] === 'full') {
                $this->addFieldViolation($restriction['field'], $constraint->updateFieldMessage);
            } else {
                $this->validateAllowedValues($object, $constraint->updateFieldMessage, $restriction);
            }
        }
    }

    /**
     * @param $object
     *
     * @return array
     */
    protected function getEntityChangeSet($object)
    {
        $changesSet = [];
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $originalData = $unitOfWork->getOriginalEntityData($object);

        $class =  $this->doctrineHelper->getEntityClass($object);
        $fieldList = $this->fieldHelper->getFields($class, true);

        foreach ($fieldList as $field) {
            $fieldName = $field['name'];
            // skip field, its a partially omitted one!
            if (! (isset($originalData[$fieldName]) || array_key_exists($fieldName, $originalData))) {
                continue;
            }

            $actualValue = $this->fieldHelper->getObjectValue($object, $fieldName);
            $originalValue = $originalData[$fieldName];

            if ($actualValue !== $originalValue) {
                $changesSet[$fieldName] = [$originalValue, $actualValue];
            }
        }

        return $changesSet;
    }

    /**
     * @param object $object
     * @param string $message
     * @param array  $restriction
     */
    protected function validateAllowedValues($object, $message, $restriction)
    {
        $fieldValue = $this->propertyAccessor->getValue($object, $restriction['field']);
        if (is_object($fieldValue)) {
            $fieldValue = $this->doctrineHelper->isManageableEntity($fieldValue) ?
                $this->doctrineHelper->getSingleEntityIdentifier($fieldValue) :
                (string)$fieldValue;
        }

        if ($restriction['mode'] === 'allow') {
            if (!in_array($fieldValue, $restriction['values'], true)) {
                $this->addFieldViolation($restriction['field'], $message);
            }
        } else {
            if (in_array($fieldValue, $restriction['values'], true)) {
                $this->addFieldViolation($restriction['field'], $message);
            }
        }
    }

    /**
     * @param string $field
     * @param string $message
     */
    protected function addFieldViolation($field, $message)
    {
        /** @var ExecutionContextInterface $context */
        $context = $this->context;
        $context
            ->buildViolation($message)
            ->atPath($field)
            ->addViolation();
    }
}
