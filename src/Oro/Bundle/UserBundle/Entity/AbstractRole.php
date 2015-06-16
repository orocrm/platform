<?php

namespace Oro\Bundle\UserBundle\Entity;

use Symfony\Component\Security\Core\Role\Role as BaseRole;

abstract class AbstractRole extends BaseRole
{
    /**
     * @var string
     */
    protected $role;

    /**
     * @return string
     */
    abstract public function getPrefix();

    /**
     * Set role name only for newly created role
     *
     * @param  string $role Role name
     * @return $this
     */
    public function setRole($role)
    {
        $this->role = (string)strtoupper(preg_replace('/[^\w\-]/i', '_', uniqid($role . '_')));

        // every role should be prefixed with role prefix
        if (strpos($this->role, $this->getPrefix()) !== 0) {
            $this->role = $this->getPrefix() . $this->role;
        }

        return $this;
    }

    /**
     * @return string
     */
    abstract public function getLabel();

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->getLabel();
    }
}
