<?php

namespace Oro\Bundle\UserBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;

use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin;
use Oro\Bundle\ImapBundle\Entity\ImapEmailOrigin;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Entity\UserApi;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\Group;
use Oro\Bundle\UserBundle\Entity\Status;
use Oro\Bundle\UserBundle\Entity\Email;
use Oro\Bundle\OrganizationBundle\Entity\BusinessUnit;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
class UserTest extends \PHPUnit_Framework_TestCase
{
    public function testUsername()
    {
        $user = new User;
        $name = 'Tony';

        $this->assertNull($user->getUsername());

        $user->setUsername($name);

        $this->assertEquals($name, $user->getUsername());
        $this->assertEquals($name, $user);
    }

    public function testEmail()
    {
        $user = new User;
        $mail = 'tony@mail.org';

        $this->assertNull($user->getEmail());

        $user->setEmail($mail);

        $this->assertEquals($mail, $user->getEmail());
    }

    public function testIsPasswordRequestNonExpired()
    {
        $user      = new User;
        $requested = new \DateTime('-10 seconds');

        $user->setPasswordRequestedAt($requested);

        $this->assertSame($requested, $user->getPasswordRequestedAt());
        $this->assertTrue($user->isPasswordRequestNonExpired(15));
        $this->assertFalse($user->isPasswordRequestNonExpired(5));
    }

    public function testIsPasswordRequestAtCleared()
    {
        $user = new User;
        $requested = new \DateTime('-10 seconds');

        $user->setPasswordRequestedAt($requested);
        $user->setPasswordRequestedAt(null);

        $this->assertFalse($user->isPasswordRequestNonExpired(15));
        $this->assertFalse($user->isPasswordRequestNonExpired(5));
    }

    public function testConfirmationToken()
    {
        $user  = new User;
        $token = $user->generateToken();

        $this->assertNotEmpty($token);

        $user->setConfirmationToken($token);

        $this->assertEquals($token, $user->getConfirmationToken());
    }

    public function testSetRolesWithArrayArgument()
    {
        $roles = array(new Role(User::ROLE_DEFAULT));
        $user = new User;
        $this->assertEmpty($user->getRoles());
        $user->setRoles($roles);
        $this->assertEquals($roles, $user->getRoles());
    }

    public function testSetRolesWithCollectionArgument()
    {
        $roles = new ArrayCollection(array(new Role(User::ROLE_DEFAULT)));
        $user = new User;
        $this->assertEmpty($user->getRoles());
        $user->setRoles($roles);
        $this->assertEquals($roles->toArray(), $user->getRoles());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $roles must be an instance of Doctrine\Common\Collections\Collection or an array
     */
    public function testSetRolesThrowsInvalidArgumentException()
    {
        $user = new User;
        $user->setRoles('roles');
    }

    public function testHasRoleWithStringArgument()
    {
        $user = new User;
        $role = new Role(User::ROLE_DEFAULT);

        $this->assertFalse($user->hasRole(User::ROLE_DEFAULT));
        $user->addRole($role);
        $this->assertTrue($user->hasRole(User::ROLE_DEFAULT));
    }

    public function testHasRoleWithObjectArgument()
    {
        $user = new User;
        $role = new Role(User::ROLE_DEFAULT);

        $this->assertFalse($user->hasRole($role));
        $user->addRole($role);
        $this->assertTrue($user->hasRole($role));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $role must be an instance of Oro\Bundle\UserBundle\Entity\Role or a string
     */
    public function testHasRoleThrowsInvalidArgumentException()
    {
        $user = new User;
        $user->hasRole(new \stdClass());
    }

    public function testRemoveRoleWithStringArgument()
    {
        $user = new User;
        $role = new Role(User::ROLE_DEFAULT);
        $user->addRole($role);

        $this->assertTrue($user->hasRole($role));
        $user->removeRole(User::ROLE_DEFAULT);
        $this->assertFalse($user->hasRole($role));
    }

    public function testRemoveRoleWithObjectArgument()
    {
        $user = new User;
        $role = new Role(User::ROLE_DEFAULT);
        $user->addRole($role);

        $this->assertTrue($user->hasRole($role));
        $user->removeRole($role);
        $this->assertFalse($user->hasRole($role));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $role must be an instance of Oro\Bundle\UserBundle\Entity\Role or a string
     */
    public function testRemoveRoleThrowsInvalidArgumentException()
    {
        $user = new User;
        $user->removeRole(new \stdClass());
    }

    public function testSetRolesCollection()
    {
        $user = new User;
        $role = new Role(User::ROLE_DEFAULT);
        $roles = new ArrayCollection(array($role));
        $user->setRolesCollection($roles);
        $this->assertSame($roles, $user->getRolesCollection());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $collection must be an instance of Doctrine\Common\Collections\Collection
     */
    public function testSetRolesCollectionThrowsException()
    {
        $user = new User();
        $user->setRolesCollection(array());
    }

    public function testGroups()
    {
        $user  = new User;
        $role  = new Role('ROLE_FOO');
        $group = new Group('Users');

        $group->addRole($role);

        $this->assertNotContains($role, $user->getRoles());

        $user->addGroup($group);

        $this->assertContains($group, $user->getGroups());
        $this->assertContains('Users', $user->getGroupNames());
        $this->assertTrue($user->hasRole($role));
        $this->assertTrue($user->hasGroup('Users'));

        $user->removeGroup($group);

        $this->assertFalse($user->hasRole($role));
    }

    public function testIsEnabled()
    {
        $user = new User;

        $this->assertTrue($user->isEnabled());
        $this->assertTrue($user->isAccountNonExpired());
        $this->assertTrue($user->isAccountNonLocked());

        $user->setEnabled(false);

        $this->assertFalse($user->isEnabled());
        $this->assertFalse($user->isAccountNonLocked());
    }

    public function testSerializing()
    {
        $user  = new User;
        $clone = clone $user;
        $data  = $user->serialize();

        $this->assertNotEmpty($data);

        $user->setPassword('new-pass')
             ->setConfirmationToken('token')
             ->setUsername('new-name');

        $user->unserialize($data);

        $this->assertEquals($clone, $user);
    }

    public function testPassword()
    {
        $user = new User;
        $pass = 'anotherPassword';

        $user->setPassword($pass);
        $user->setPlainPassword($pass);

        $this->assertEquals($pass, $user->getPassword());
        $this->assertEquals($pass, $user->getPlainPassword());

        $user->eraseCredentials();

        $this->assertNull($user->getPlainPassword());
    }

    public function testCallbacks()
    {
        $user = new User;
        $user->beforeSave();
        $this->assertInstanceOf('\DateTime', $user->getCreatedAt());
    }

    public function testStatuses()
    {
        $user  = new User;
        $status  = new Status();

        $this->assertNotContains($status, $user->getStatuses());
        $this->assertNull($user->getCurrentStatus());

        $user->addStatus($status);
        $user->setCurrentStatus($status);

        $this->assertContains($status, $user->getStatuses());
        $this->assertEquals($status, $user->getCurrentStatus());

        $user->setCurrentStatus();

        $this->assertNull($user->getCurrentStatus());

        $user->getStatuses()->clear();

        $this->assertNotContains($status, $user->getStatuses());
    }

    public function testEmails()
    {
        $user  = new User;
        $email  = new Email();

        $this->assertNotContains($email, $user->getEmails());

        $user->addEmail($email);

        $this->assertContains($email, $user->getEmails());

        $user->removeEmail($email);

        $this->assertNotContains($email, $user->getEmails());
    }

    public function testNames()
    {
        $user  = new User();
        $first = 'James';
        $last  = 'Bond';

        $user->setFirstName($first);
        $user->setLastName($last);
    }

    public function testDates()
    {
        $user = new User;
        $now  = new \DateTime('-1 year');

        $user->setBirthday($now);
        $user->setLastLogin($now);

        $this->assertEquals($now, $user->getBirthday());
        $this->assertEquals($now, $user->getLastLogin());
    }

    public function testApi()
    {
        $user = new User;
        $api  = new UserApi();

        $this->assertNull($user->getApi());

        $user->setApi($api);

        $this->assertEquals($api, $user->getApi());
    }

    public function testUnserialize()
    {
        $user = new User();
        $serialized = array(
            'password',
            'salt',
            'username',
            true,
            'confirmation_token',
            10
        );
        $user->unserialize(serialize($serialized));

        $this->assertEquals($serialized[0], $user->getPassword());
        $this->assertEquals($serialized[1], $user->getSalt());
        $this->assertEquals($serialized[2], $user->getUsername());
        $this->assertEquals($serialized[3], $user->isEnabled());
        $this->assertEquals($serialized[4], $user->getConfirmationToken());
        $this->assertEquals($serialized[5], $user->getId());
    }

    public function testIsCredentialsNonExpired()
    {
        $user = new User();
        $this->assertTrue($user->isCredentialsNonExpired());
    }

    /**
     * @dataProvider provider
     * @param string $property
     * @param mixed  $value
     */
    public function testSettersAndGetters($property, $value)
    {
        $obj = new User();

        call_user_func_array(array($obj, 'set' . ucfirst($property)), array($value));
        $this->assertEquals($value, call_user_func_array(array($obj, 'get' . ucfirst($property)), array()));
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function provider()
    {
        return array(
            array('username', 'test'),
            array('email', 'test'),
            array('nameprefix', 'test'),
            array('firstname', 'test'),
            array('middlename', 'test'),
            array('lastname', 'test'),
            array('namesuffix', 'test'),
            array('birthday', new \DateTime()),
            array('password', 'test'),
            array('plainPassword', 'test'),
            array('confirmationToken', 'test'),
            array('passwordRequestedAt', new \DateTime()),
            array('lastLogin', new \DateTime()),
            array('loginCount', 11),
            array('createdAt', new \DateTime()),
            array('updatedAt', new \DateTime()),
        );
    }

    public function testPreUpdate()
    {
        $user = new User();
        $user->preUpdate();
        $this->assertInstanceOf('\DateTime', $user->getUpdatedAt());
    }

    public function testBusinessUnit()
    {
        $user  = new User;
        $businessUnit = new BusinessUnit();

        $user->setBusinessUnits(new ArrayCollection(array($businessUnit)));

        $this->assertContains($businessUnit, $user->getBusinessUnits());

        $user->removeBusinessUnit($businessUnit);

        $this->assertNotContains($businessUnit, $user->getBusinessUnits());

        $user->addBusinessUnit($businessUnit);

        $this->assertContains($businessUnit, $user->getBusinessUnits());
    }

    public function testOwners()
    {
        $entity = new User();
        $businessUnit = new BusinessUnit();

        $this->assertEmpty($entity->getOwner());

        $entity->setOwner($businessUnit);

        $this->assertEquals($businessUnit, $entity->getOwner());
    }

    public function testImapConfiguration()
    {
        $entity = new User();
        $imapConfiguration = $this->getMock('Oro\Bundle\ImapBundle\Entity\ImapEmailOrigin');
        $imapConfiguration->expects($this->once())
            ->method('setIsActive')
            ->with(false);

        $this->assertCount(0, $entity->getEmailOrigins());
        $this->assertNull($entity->getImapConfiguration());

        $entity->setImapConfiguration($imapConfiguration);
        $this->assertEquals($imapConfiguration, $entity->getImapConfiguration());
        $this->assertCount(1, $entity->getEmailOrigins());

        $entity->setImapConfiguration(null);
        $this->assertNull($entity->getImapConfiguration());
        $this->assertCount(0, $entity->getEmailOrigins());
    }

    public function testEmailOrigins()
    {
        $entity = new User();
        $origin1 = new InternalEmailOrigin();
        $origin2 = new InternalEmailOrigin();

        $this->assertCount(0, $entity->getEmailOrigins());

        $entity->addEmailOrigin($origin1);
        $entity->addEmailOrigin($origin2);
        $this->assertCount(2, $entity->getEmailOrigins());
        $this->assertSame($origin1, $entity->getEmailOrigins()->first());
        $this->assertSame($origin2, $entity->getEmailOrigins()->last());

        $entity->removeEmailOrigin($origin1);
        $this->assertCount(1, $entity->getEmailOrigins());
        $this->assertSame($origin2, $entity->getEmailOrigins()->first());
    }

    public function testGetApiKey()
    {
        $entity = new User();

        $this->assertNotEmpty($entity->getApiKey(), 'Should return some key, even if is not present');
        $key1 = $entity->getApiKey();
        usleep(1); // need because 'uniqid' generates a unique identifier based on the current time in microseconds
        $this->assertNotSame($key1, $entity->getApiKey(), 'Should return unique random string');

        $apiKey = new UserApi();
        $apiKey->setApiKey($apiKey->generateKey());
        $entity->setApi($apiKey);

        $this->assertSame($apiKey->getApiKey(), $entity->getApiKey(), 'Should delegate call to userApi entity');
    }
}
