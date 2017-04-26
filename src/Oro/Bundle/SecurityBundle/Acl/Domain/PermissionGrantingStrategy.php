<?php

namespace Oro\Bundle\SecurityBundle\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Model\PermissionGrantingStrategyInterface;
use Symfony\Component\Security\Acl\Model\AclInterface;
use Symfony\Component\Security\Acl\Model\EntryInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;
use Symfony\Component\Security\Acl\Model\AuditLoggerInterface;
use Symfony\Component\Security\Acl\Exception\NoAceFoundException;

use Oro\Component\DependencyInjection\ServiceLink;
use Oro\Bundle\SecurityBundle\Acl\Extension\AclExtensionInterface;
use Oro\Bundle\SecurityBundle\Metadata\EntitySecurityMetadataProvider;

/**
 * The ACL extensions based permission granting strategy to apply to the access control list.
 * The default Symfony permission granting strategy is supported as well.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PermissionGrantingStrategy implements PermissionGrantingStrategyInterface
{
    const ALL   = 'all';
    const ANY   = 'any';
    const EQUAL = 'equal';

    /** @var AuditLoggerInterface */
    protected $auditLogger;

    /** @var ServiceLink */
    private $securityMetadataProviderLink;

    /** @var ServiceLink */
    private $contextLink;

    /**
     * Sets the audit logger
     *
     * @param AuditLoggerInterface $auditLogger
     */
    public function setAuditLogger(AuditLoggerInterface $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param ServiceLink $securityMetadataProviderLink
     */
    public function setSecurityMetadataProvider(ServiceLink $securityMetadataProviderLink)
    {
        $this->securityMetadataProviderLink = $securityMetadataProviderLink;
    }

    /**
     * Sets the accessor to the context data of this strategy
     *
     * @param ServiceLink $contextLink The link to a service implementing PermissionGrantingStrategyContextInterface
     */
    public function setContext(ServiceLink $contextLink)
    {
        $this->contextLink = $contextLink;
    }

    /**
     * Gets context this strategy is working in
     *
     * @return PermissionGrantingStrategyContextInterface
     * @throws \RuntimeException
     */
    public function getContext()
    {
        if ($this->contextLink === null) {
            throw new \RuntimeException('The context link is not set.');
        }

        return $this->contextLink->getService();
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted(AclInterface $acl, array $masks, array $sids, $administrativeMode = false)
    {
        $result = null;

        // check object ACEs
        $aces = $acl->getObjectAces();
        if (!empty($aces)) {
            $result = $this->hasSufficientPermissions($acl, $aces, $masks, $sids, $administrativeMode);
        }
        // check class ACEs if object ACEs were not found
        if ($result === null) {
            $aces = $acl->getClassAces();
            if (!empty($aces)) {
                $result = $this->hasSufficientPermissions($acl, $aces, $masks, $sids, $administrativeMode);
            }
        }
        // check parent ACEs if object and class ACEs were not found
        if ($result === null && $acl->isEntriesInheriting()) {
            $parentAcl = $acl->getParentAcl();
            if ($parentAcl !== null) {
                $result = $parentAcl->isGranted($masks, $sids, $administrativeMode);
            }
        }
        // throw NoAceFoundException if no any ACEs were found
        if ($result === null) {
            throw new NoAceFoundException();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function isFieldGranted(AclInterface $acl, $field, array $masks, array $sids, $administrativeMode = false)
    {
        $result = null;

        // check if field security metadata has alias and if so - use it instead of field being passed
        $type = $acl->getObjectIdentity()->getType();
        $securityMetadataProvider = $this->getSecurityMetadataProvider();
        if ($securityMetadataProvider->isProtectedEntity($type)) {
            $entityMetadata = $securityMetadataProvider->getMetadata($type);
            $entityFieldsMetadata = $entityMetadata->getFields();
            if (isset($entityFieldsMetadata[$field])) {
                $field = $entityFieldsMetadata[$field]->getAlias() ? : $field;
            }
        }

        // check object ACEs
        $aces = $acl->getObjectFieldAces($field);
        if (!empty($aces)) {
            $result = $this->hasSufficientPermissions($acl, $aces, $masks, $sids, $administrativeMode);
        }
        // check class ACEs if object ACEs were not found
        if ($result === null) {
            $aces = $acl->getClassFieldAces($field);
            if (!empty($aces)) {
                $result = $this->hasSufficientPermissions($acl, $aces, $masks, $sids, $administrativeMode);
            }
        }

        // check parent ACEs if object and class ACEs were not found
        if ($result === null && $acl->isEntriesInheriting()) {
            $parentAcl = $acl->getParentAcl();
            if ($parentAcl !== null) {
                $result = $parentAcl->isFieldGranted($field, $masks, $sids, $administrativeMode);
            }
        }

        // return true if no any ACEs were found (grant access)
        if ($result === null) {
            return true;
        }

        return $result;
    }

    /**
     * Makes an authorization decision.
     *
     * The order of ACEs, and SIDs is significant; the order of permission masks
     * not so much. It is important to note that the more specific security
     * identities should be at the beginning of the SIDs array in order for this
     * strategy to produce intuitive authorization decisions.
     *
     * @param AclInterface                $acl
     * @param EntryInterface[]            $aces               An array of ACE to check against
     * @param array                       $masks              An array of permission masks
     * @param SecurityIdentityInterface[] $sids               An array of SecurityIdentityInterface implementations
     * @param boolean                     $administrativeMode True turns off audit logging
     *
     * @return boolean|null true if granting access; false if denying access; null if ACE was not found.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function hasSufficientPermissions(
        AclInterface $acl,
        array $aces,
        array $masks,
        array $sids,
        $administrativeMode
    ) {
        $context = $this->getContext();
        $extension = $context->getAclExtension();
        $oid = $acl->getObjectIdentity();
        $isRootOid = ObjectIdentityFactory::ROOT_IDENTITY_TYPE === $oid->getType();
        if ($isRootOid && $oid->getIdentifier() !== $extension->getExtensionKey()) {
            return null;
        }

        $securityToken = $context->getSecurityToken();
        $object = $context->getObject();
        if ($object instanceof DomainObjectWrapper) {
            $object = $object->getDomainObject();
        }

        $result = false;
        $triggeredAce = null;
        $triggeredMask = 0;

        foreach ($sids as $sid) {
            foreach ($aces as $ace) {
                if (!$sid->equals($ace->getSecurityIdentity())) {
                    continue;
                }

                $aceMaskServiceBits = $ace->getMask();
                if ($isRootOid && null !== $object) {
                    $aceMaskServiceBits = $extension->adaptRootMask($aceMaskServiceBits, $object);
                }
                $aceMaskServiceBits = $extension->getServiceBits($aceMaskServiceBits);
                foreach ($masks as $requiredMask) {
                    if ($extension->getServiceBits($requiredMask) !== $aceMaskServiceBits) {
                        continue;
                    }

                    if ($this->isAceApplicable($requiredMask, $ace, $extension)) {
                        $isGranting = $ace->isGranting();
                        if ($sid instanceof RoleSecurityIdentity) {
                            // give an additional chance for the appropriate ACL extension to decide
                            // whether an access to a domain object is granted or not
                            $decisionResult = $extension->decideIsGranting($requiredMask, $object, $securityToken);
                            if (!$decisionResult) {
                                $isGranting = !$isGranting;
                            }
                        }

                        if ($isGranting) {
                            // the access is granted if there is at least one granting ACE
                            if (null === $triggeredAce
                                || $extension->getAccessLevel($requiredMask, null, $object)
                                > $extension->getAccessLevel($triggeredMask, null, $object)
                            ) {
                                // the current ACE gives more permissions than previous one
                                $triggeredAce = $ace;
                                $triggeredMask = $requiredMask;
                            }

                            $result = true;
                        } elseif (null === $triggeredAce) {
                            // remember the first denying ACE
                            $triggeredAce = $ace;
                            $triggeredMask = $requiredMask;
                        }
                    } elseif ($this->containsExtraPermissions($requiredMask, $ace, $extension)) {
                        $triggeredAce = $ace;
                        $triggeredMask = $requiredMask;
                    }
                }
            }
        }

        if (null === $triggeredAce) {
            // ACE was not found
            return null;
        } else {
            $context->setTriggeredMask(
                $triggeredMask,
                $extension->getAccessLevel($triggeredMask, null, $object)
            );
        }

        if (!$administrativeMode && null !== $this->auditLogger) {
            $this->auditLogger->logIfNeeded($result, $triggeredAce);
        }

        return $result;
    }

    /**
     * Determines whether the ACE is applicable to the given permission/security identity combination.
     *
     * Strategy ALL:
     *     The ACE will be considered applicable when all the turned-on bits in the
     *     required mask are also turned-on in the ACE mask.
     *
     * Strategy ANY:
     *     The ACE will be considered applicable when any of the turned-on bits in
     *     the required mask is also turned-on the in the ACE mask.
     *
     * Strategy EQUAL:
     *     The ACE will be considered applicable when the bitmasks are equal.
     *
     * @param integer               $requiredMask
     * @param EntryInterface        $ace
     * @param AclExtensionInterface $extension
     *
     * @return bool
     * @throws \RuntimeException if the ACE strategy is not supported
     */
    protected function isAceApplicable($requiredMask, EntryInterface $ace, AclExtensionInterface $extension)
    {
        $requiredMask = $extension->removeServiceBits($requiredMask);
        $aceMask = $extension->removeServiceBits($ace->getMask());
        $strategy = $ace->getStrategy();
        switch ($strategy) {
            case self::ALL:
                return $requiredMask === ($aceMask & $requiredMask);
            case self::ANY:
                return 0 !== ($aceMask & $requiredMask);
            case self::EQUAL:
                return $requiredMask === $aceMask;
            default:
                throw new \RuntimeException(sprintf('The strategy "%s" is not supported.', $strategy));
        }
    }

    /**
     * Checks whether the required mask has at least one permission that does not exist in the given ACE mask
     *
     * @param int                   $requiredMask
     * @param EntryInterface        $ace
     * @param AclExtensionInterface $extension
     *
     * @return bool
     */
    protected function containsExtraPermissions($requiredMask, EntryInterface $ace, AclExtensionInterface $extension)
    {
        $aceMask = $ace->getMask();

        return
            $requiredMask !== $aceMask
            && 0 !== $extension->removeServiceBits($requiredMask)
            && !empty(array_diff(
                $extension->getPermissions($requiredMask, true),
                $extension->getPermissions($aceMask, true)
            ));
    }

    /**
     * @return EntitySecurityMetadataProvider
     */
    protected function getSecurityMetadataProvider()
    {
        return $this->securityMetadataProviderLink->getService();
    }
}
