<?php

namespace Oro\Bundle\TagBundle\Tests\Selenium\Pages;

use Oro\Bundle\TestFrameworkBundle\Pages\AbstractPageFilteredGrid;

/**
 * Class Tags
 *
 * @package Oro\Bundle\TestFrameworkBundle\Pages\Objects
 * @method Tags openTags() openTags(string)
 * {@inheritdoc}
 */
class Tags extends AbstractPageFilteredGrid
{
    const URL = 'tag';

    public function __construct($testCase, $redirect = true)
    {
        $this->redirectUrl = self::URL;
        parent::__construct($testCase, $redirect);
    }

    /**
     * @param bool $new
     *
     * @return Tag
     */
    public function add($new = true)
    {
        $this->test->byXPath("//a[@title='Create Tag']")->click();
        $this->waitPageToLoad();
        $this->waitForAjax();
        $tag = new Tag($this->test);
        return $tag->init($new);
    }

    /**
     * @param array $entityData
     *
     * @return Tag
     */
    public function open($entityData = array())
    {
        $contact = $this->getEntity($entityData);
        $contact->click();
        $this->waitPageToLoad();
        $this->waitForAjax();

        return new Tag($this->test);
    }

    /**
     * @return Tag
     */
    public function edit()
    {
        $menu = $this->test->byXpath("//td[contains(@class,'action-cell')]//a[contains(., '...')]");
        $this->test->moveto($menu);
        $this->test->byXpath("//td[contains(@class,'action-cell')]//a[@title= 'Edit']")->click();
        $this->waitPageToLoad();
        $this->waitForAjax();
        $tag = new Tag($this->test);

        return $tag->init();
    }

    public function delete()
    {
        $menu = $this->test->byXpath("//td[contains(@class,'action-cell')]//a[contains(., '...')]");
        $this->test->moveto($menu);
        $this->test->byXpath("//td[contains(@class,'action-cell')]//a[@title= 'Delete']")->click();
        $this->test->byXpath("//div[div[contains(., 'Delete Confirmation')]]//a[text()='Yes, Delete']")->click();
        $this->waitPageToLoad();
        $this->waitForAjax();

        return $this;
    }

    public function checkContextMenu($tagName, $contextName)
    {
        $this->filterBy('Tag', $tagName);
        $this->test->byXpath("//td[contains(@class,'action-cell')]//a[contains(., '...')]")->click();
        $this->waitForAjax();
        $this->assertElementNotPresent("//td[contains(@class,'action-cell')]//a[@title= '{$contextName}']");
    }
}
