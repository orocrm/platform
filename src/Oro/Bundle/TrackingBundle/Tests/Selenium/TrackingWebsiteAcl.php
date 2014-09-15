<?php

namespace Oro\Bundle\TrackingBundle\Tests\Selenium;

use Oro\Bundle\TestFrameworkBundle\Test\Selenium2TestCase;
use Oro\Bundle\TrackingBundle\Tests\Selenium\Pages\TrackingWebsites;

/**
 * Class TrackingWebsiteAcl
 *
 * @package Oro\Bundle\TrackingBundle\Tests\Selenium
 * {@inheritdoc}
 */
class TrackingWebsiteAcl extends Selenium2TestCase
{
    public function testCreateRole()
    {
        $randomPrefix = mt_rand();
        $login = $this->login();
        $login->openRoles('Oro\Bundle\UserBundle')
            ->add()
            ->setLabel('Label_' . $randomPrefix)
            ->setOwner('Main')
            ->setEntity('Tracking Website', array('Create', 'Edit', 'Delete', 'View', 'Assign'), 'System')
            ->assertTitle('Create Role - Roles - User Management - System')
            ->save()
            ->assertMessage('Role saved')
            ->assertTitle('Roles - User Management - System')
            ->close();

        return ($randomPrefix);
    }

    /**
     * @depends testCreateRole
     * @param $role
     * @return string
     */
    public function testCreateUser($role)
    {
        $username = 'User_'.mt_rand();

        $login = $this->login();
        $login->openUsers('Oro\Bundle\UserBundle')
            ->add()
            ->assertTitle('Create User - Users - User Management - System')
            ->setUsername($username)
            ->enable()
            ->setOwner('Main')
            ->setFirstpassword('123123q')
            ->setSecondpassword('123123q')
            ->setFirstName('First_'.$username)
            ->setLastName('Last_'.$username)
            ->setEmail($username.'@mail.com')
            ->setRoles(array('Label_' . $role))
            ->uncheckInviteUser()
            ->save()
            ->assertMessage('User saved')
            ->toGrid()
            ->close()
            ->assertTitle('Users - User Management - System');

        return $username;
    }

    /**
     * @return string
     */
    public function testCreateTrackingWebsite()
    {
        $identifier = 'Website' . mt_rand(10, 99);

        $login = $this->login();
        /** @var TrackingWebsites $login */
        $login->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->assertTitle('Tracking Websites - Marketing')
            ->add()
            ->assertTitle('Create Tracking Website - Tracking Websites - Marketing')
            ->setName($identifier)
            ->setIdentifier($identifier)
            ->setUrl("http://{$identifier}.com")
            ->save()
            ->assertMessage('Tracking Website saved')
            ->assertTitle("{$identifier} - Tracking Websites - Marketing");

        return $identifier;
    }


    /**
     * @depends testCreateUser
     * @depends testCreateRole
     * @depends testCreateTrackingWebsite
     *
     * @param $aclCase
     * @param $username
     * @param $role
     * @param $identifier
     *
     * @dataProvider columnTitle
     */
    public function testCaseAcl($aclCase, $username, $role, $identifier)
    {
        $roleName = 'Label_' . $role;
        $login = $this->login();
        switch ($aclCase) {
            case 'delete':
                $this->deleteAcl($login, $roleName, $username, $identifier);
                break;
            case 'update':
                $this->updateAcl($login, $roleName, $username, $identifier);
                break;
            case 'create':
                $this->createAcl($login, $roleName, $username);
                break;
            case 'view':
                $this->viewAcl($login, $username, $roleName, $identifier);
                break;
        }
    }

    public function deleteAcl($login, $roleName, $username, $identifier)
    {
        $login->openRoles('Oro\Bundle\UserBundle')
            ->filterBy('Label', $roleName)
            ->open(array($roleName))
            ->setEntity('Tracking Website', array('Delete'), 'None')
            ->save()
            ->logout()
            ->setUsername($username)
            ->setPassword('123123q')
            ->submit()
            ->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->filterBy('Identifier', $identifier)
            ->checkActionMenu('Delete')
            ->open(array($identifier))
            ->assertElementNotPresent(
                "//div[@class='pull-left btn-group icons-holder']/a[@title='Delete Tracking Website']"
            );
    }

    public function updateAcl($login, $roleName, $username, $identifier)
    {
        $login->openRoles('Oro\Bundle\UserBundle')
            ->filterBy('Label', $roleName)
            ->open(array($roleName))
            ->setEntity('Tracking Website', array('Edit'), 'None')
            ->save()
            ->logout()
            ->setUsername($username)
            ->setPassword('123123q')
            ->submit()
            ->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->filterBy('Identifier', $identifier)
            ->checkActionMenu('Update')
            ->open(array($identifier))
            ->assertElementNotPresent(
                "//div[@class='pull-left btn-group icons-holder']/a[@title = 'Edit Tracking Website']"
            );
    }

    public function createAcl($login, $roleName, $username)
    {
        $login->openRoles('Oro\Bundle\UserBundle')
            ->filterBy('Label', $roleName)
            ->open(array($roleName))
            ->setEntity('Tracking Website', array('Create'), 'None')
            ->save()
            ->logout()
            ->setUsername($username)
            ->setPassword('123123q')
            ->submit()
            ->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->assertElementNotPresent(
                "//div[@class='pull-right title-buttons-container']//a[contains(., 'Create Tracking Website')]"
            );
    }

    public function viewAcl($login, $username, $roleName)
    {
        $login->openRoles('Oro\Bundle\UserBundle')
            ->filterBy('Label', $roleName)
            ->open(array($roleName))
            ->setEntity('Tracking Website', array('View'), 'None')
            ->save()
            ->logout()
            ->setUsername($username)
            ->setPassword('123123q')
            ->submit()
            ->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->assertTitle('403 - Forbidden');
    }

    /**
     * Data provider for Tags ACL test
     *
     * @return array
     */
    public function columnTitle()
    {
        return array(
            'delete' => array('delete'),
            'update' => array('update'),
            'create' => array('create'),
            'view' => array('view'),
        );
    }
}
