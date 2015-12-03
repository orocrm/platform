<?php

namespace Oro\Bundle\SecurityBundle\ORM\SqlFilter;

use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Domain\ObjectIdAccessor;
use Oro\Bundle\SecurityBundle\Acl\Domain\OneShotIsGrantedObserver;
use Oro\Bundle\SecurityBundle\Acl\Voter\AclVoter;
use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadata;
use Oro\Bundle\SecurityBundle\Owner\Metadata\OwnershipMetadataProvider;
use Oro\Bundle\SecurityBundle\Owner\OwnerTree;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class OwnershipFilterBuilder
{
    /**
     * @var ServiceLink
     */
    protected $securityContextLink;

    /**
     * @var ObjectIdAccessor
     */
    protected $objectIdAccessor;

    /**
     * @var AclVoter
     */
    protected $aclVoter;

    /**
     * @var OwnershipMetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var EntitySecurityMetadataProvider
     */
    protected $entityMetadataProvider;

    /**
     * @var OwnerTree
     */
    protected $tree;

    /**
     * Constructor
     *
     * @param ServiceLink                    $securityContextLink
     * @param ObjectIdAccessor               $objectIdAccessor
     * @param EntitySecurityMetadataProvider $entityMetadataProvider
     * @param OwnershipMetadataProvider      $metadataProvider
     * @param OwnerTree                      $tree
     * @param AclVoter                       $aclVoter
     */
    public function __construct(
        ServiceLink $securityContextLink,
        ObjectIdAccessor $objectIdAccessor,
        EntitySecurityMetadataProvider $entityMetadataProvider,
        OwnershipMetadataProvider $metadataProvider,
        OwnerTree $tree,
        AclVoter $aclVoter = null
    ) {
        $this->securityContextLink = $securityContextLink;
        $this->aclVoter = $aclVoter;
        $this->objectIdAccessor = $objectIdAccessor;
        $this->entityMetadataProvider = $entityMetadataProvider;
        $this->metadataProvider = $metadataProvider;
        $this->tree = $tree;
    }

    /**
     * Gets the SQL query part to add to a query.
     *
     * @param  string $targetEntityClassName
     * @param  string $targetTableAlias
     * @return string The constraint SQL if there is available, empty string otherwise
     */
    public function buildFilterConstraint($targetEntityClassName, $targetTableAlias)
    {
        if ($this->aclVoter === null
            || !$this->getUserId()
            || !$this->entityMetadataProvider->isProtectedEntity($targetEntityClassName)
        ) {
            return '';
        }

        $observer = new OneShotIsGrantedObserver();
        $this->aclVoter->addOneShotIsGrantedObserver($observer);
        $isGranted = $this->getSecurityContext()->isGranted('VIEW', 'entity:' . $targetEntityClassName);

        $constraint = null;

        if ($isGranted) {
            $constraint = $this->buildConstraintIfAccessIsGranted(
                $targetEntityClassName,
                $targetTableAlias,
                $observer->getAccessLevel(),
                $this->metadataProvider->getMetadata($targetEntityClassName)
            );
        }

        if ($constraint === null) {
            // "deny access" SQL condition
            $constraint = empty($targetTableAlias)
                ? '1 = 0'
                // added to see all tables aliases with denied permissions
                : sprintf('\'%s\' = \'\'', $targetTableAlias);
        }

        return $constraint;
    }

    /**
     * @param  string            $targetEntityClassName
     * @param  string            $targetTableAlias
     * @param  int               $accessLevel
     * @param  OwnershipMetadata $metadata
     * @return string|null
     *
     * The cyclomatic complexity warning is suppressed by performance reasons
     * (to avoid unnecessary cloning od arrays)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function buildConstraintIfAccessIsGranted(
        $targetEntityClassName,
        $targetTableAlias,
        $accessLevel,
        OwnershipMetadata $metadata
    ) {
        $constraint = null;

        if (AccessLevel::SYSTEM_LEVEL === $accessLevel) {
            $constraint = '';
        } elseif (!$metadata->hasOwner()) {
            if (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getOrganizationClass() === $targetEntityClassName) {
                    $orgIds = $this->tree->getUserOrganizationIds($this->getUserId());
                    $constraint = $this->getCondition($orgIds, $metadata, $targetTableAlias, 'id');
                } else {
                    $constraint = '';
                }
            } else {
                $constraint = '';
            }
        } else {
            if (AccessLevel::BASIC_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getUserClass() === $targetEntityClassName) {
                    $constraint = $this->getCondition($this->getUserId(), $metadata, $targetTableAlias, 'id');
                } elseif ($metadata->isUserOwned()) {
                    $constraint = $this->getCondition($this->getUserId(), $metadata, $targetTableAlias);
                }
            } elseif (AccessLevel::LOCAL_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getBusinessUnitClass() === $targetEntityClassName) {
                    $buIds = $this->tree->getUserBusinessUnitIds($this->getUserId());
                    $constraint = $this->getCondition($buIds, $metadata, $targetTableAlias, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = $this->tree->getUserBusinessUnitIds($this->getUserId());
                    $constraint = $this->getCondition($buIds, $metadata, $targetTableAlias);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = array();
                    $this->fillBusinessUnitUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata, $targetTableAlias);
                }
            } elseif (AccessLevel::DEEP_LEVEL === $accessLevel) {
                if ($this->metadataProvider->getBusinessUnitClass() === $targetEntityClassName) {
                    $buIds = array();
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata, $targetTableAlias, 'id');
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = array();
                    $this->fillSubordinateBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata, $targetTableAlias);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = array();
                    $this->fillSubordinateBusinessUnitUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata, $targetTableAlias);
                }
            } elseif (AccessLevel::GLOBAL_LEVEL === $accessLevel) {
                if ($metadata->isOrganizationOwned()) {
                    $orgIds = $this->tree->getUserOrganizationIds($this->getUserId());
                    $constraint = $this->getCondition($orgIds, $metadata, $targetTableAlias);
                } elseif ($metadata->isBusinessUnitOwned()) {
                    $buIds = array();
                    $this->fillOrganizationBusinessUnitIds($this->getUserId(), $buIds);
                    $constraint = $this->getCondition($buIds, $metadata, $targetTableAlias);
                } elseif ($metadata->isUserOwned()) {
                    $userIds = array();
                    $this->fillOrganizationUserIds($this->getUserId(), $userIds);
                    $constraint = $this->getCondition($userIds, $metadata, $targetTableAlias);
                }
            }
        }

        return $constraint;
    }

    /**
     * Gets the id of logged in user
     *
     * @return int|string
     */
    public function getUserId()
    {
        $token = $this->getSecurityContext()->getToken();
        if (!$token) {
            return null;
        }
        $user = $token->getUser();
        if (!is_object($user) || !is_a($user, $this->metadataProvider->getUserClass())) {
            return null;
        }

        return $this->objectIdAccessor->getId($user);
    }

    /**
     * Adds all business unit ids within all subordinate business units the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitIds($userId, array &$result)
    {
        $buIds = $this->tree->getUserBusinessUnitIds($userId);
        $result = array_merge($buIds, array());
        foreach ($buIds as $buId) {
            $diff = array_diff($this->tree->getSubordinateBusinessUnitIds($buId), $result);
            if (!empty($diff)) {
                $result = array_merge($result, $diff);
            }
        }
    }

    /**
     * Adds all user ids within all business units the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillBusinessUnitUserIds($userId, array &$result)
    {
        foreach ($this->tree->getUserBusinessUnitIds($userId) as $buId) {
            $userIds = $this->tree->getBusinessUnitUserIds($buId);
            if (!empty($userIds)) {
                $result = array_merge($result, $userIds);
            }
        }
    }

    /**
     * Adds all user ids within all subordinate business units the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillSubordinateBusinessUnitUserIds($userId, array &$result)
    {
        $buIds = array();
        $this->fillSubordinateBusinessUnitIds($userId, $buIds);
        foreach ($buIds as $buId) {
            $userIds = $this->tree->getBusinessUnitUserIds($buId);
            if (!empty($userIds)) {
                $result = array_merge($result, $userIds);
            }
        }
    }

    /**
     * Adds all business unit ids within all organizations the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillOrganizationBusinessUnitIds($userId, array &$result)
    {
        foreach ($this->tree->getUserOrganizationIds($userId) as $orgId) {
            $buIds = $this->tree->getOrganizationBusinessUnitIds($orgId);
            if (!empty($buIds)) {
                $result = array_merge($result, $buIds);
            }
        }
    }

    /**
     * Adds all user ids within all organizations the given user is associated
     *
     * @param int|string $userId
     * @param array      $result [output]
     */
    protected function fillOrganizationUserIds($userId, array &$result)
    {
        foreach ($this->tree->getUserOrganizationIds($userId) as $orgId) {
            foreach ($this->tree->getOrganizationBusinessUnitIds($orgId) as $buId) {
                $userIds = $this->tree->getBusinessUnitUserIds($buId);
                if (!empty($userIds)) {
                    $result = array_merge($result, $userIds);
                }
            }
        }
    }

    /**
     * Gets SQL condition for the given owner id or ids
     *
     * @param  int|int[]|null    $idOrIds
     * @param  OwnershipMetadata $metadata
     * @param  string            $targetTableAlias
     * @param  string|null       $columnName
     * @return string|null       A string represents SQL condition or null if the given owner id(s) is not provided
     */
    protected function getCondition($idOrIds, OwnershipMetadata $metadata, $targetTableAlias, $columnName = null)
    {
        $result = null;
        if (!empty($idOrIds)) {
            $idOrIds = (array) $idOrIds;
            if (count($idOrIds) > 1) {
                $result = sprintf(
                    '%s IN (%s)',
                    $this->getColumnName($metadata, $targetTableAlias, $columnName),
                    implode(',', $idOrIds)
                );
            } else {
                $result = $this->getColumnName($metadata, $targetTableAlias, $columnName) . ' = ' . $idOrIds[0];
            }
        }

        return $result;
    }

    /**
     * Gets the name of owner column
     *
     * @param  OwnershipMetadata $metadata
     * @param  string            $targetTableAlias
     * @param  string|null       $columnName
     * @return string
     */
    protected function getColumnName(OwnershipMetadata $metadata, $targetTableAlias, $columnName = null)
    {
        if ($columnName === null) {
            $columnName = $metadata->getOwnerColumnName();
        }

        return empty($targetTableAlias)
            ? $columnName
            : $targetTableAlias . '.' . $columnName;
    }

    /**
     * @return SecurityContextInterface
     */
    protected function getSecurityContext()
    {
        return $this->securityContextLink->getService();
    }
}
