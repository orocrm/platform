<?php

namespace Oro\Bundle\TrackingBundle\Tests\Selenium;

use Oro\Bundle\TestFrameworkBundle\Test\Selenium2TestCase;
use Oro\Bundle\TrackingBundle\Tests\Selenium\Pages\TrackingWebsites;

/**
 * Class TrackingWebsiteTest
 *
 * @package Oro\Bundle\TrackingBundle\Tests\Selenium
 * {@inheritdoc}
 */
class TrackingWebsiteTest extends Selenium2TestCase
{
    /**
     * @return string
     */
    public function testCreate()
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
     * @depends testCreate
     * @param $identifier
     * @return string
     */
    public function testUpdate($identifier)
    {
        $newName = 'Update_'.$identifier;

        $login = $this->login();
        /** @var TrackingWebsites $login */
        $login->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->filterBy('Identifier', $identifier)
            ->open(array($identifier))
            ->assertTitle("{$identifier} - Tracking Websites - Marketing")
            ->edit()
            ->assertTitle("{$identifier} - Edit - Tracking Websites - Marketing")
            ->setName($newName)
            ->save()
            ->assertMessage('Tracking Website saved')
            ->assertTitle("{$newName} - Tracking Websites - Marketing")
            ->close();

        return $newName;
    }

    /**
     * @depends testUpdate
     * @param $name
     */
    public function testDelete($name)
    {
        $login = $this->login();
        /** @var TrackingWebsites $login */
        $login->openTrackingWebsites('Oro\Bundle\TrackingBundle')
            ->filterBy('Name', $name)
            ->open(array($name))
            ->delete()
            ->assertMessage('Tracking Website deleted');

        /** @var TrackingWebsites $login */
        $login->openTrackingWebsites('Oro\Bundle\TrackingBundle');
        if ($login->getRowsCount() > 0) {
            $login->filterBy('Name', $name)
                ->assertNoDataMessage('No entity was found to match your search');
        }
    }
}
