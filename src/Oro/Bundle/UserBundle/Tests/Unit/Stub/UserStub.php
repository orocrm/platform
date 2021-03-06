<?php

namespace Oro\Bundle\UserBundle\Tests\Unit\Stub;

use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\UserBundle\Entity\User;

class UserStub extends User
{
    protected $auth_status;

    public function __construct(?int $id = null)
    {
        parent::__construct();

        if ($id !== null) {
            $this->id = $id;
        }
    }

    public function getAuthStatus()
    {
        return $this->auth_status;
    }

    public function setAuthStatus(AbstractEnumValue $enum = null)
    {
        $this->auth_status = $enum;

        return $this;
    }
}
