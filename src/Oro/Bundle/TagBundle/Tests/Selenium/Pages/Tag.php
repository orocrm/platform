<?php

namespace Oro\Bundle\TagBundle\Tests\Selenium\Pages;

use Oro\Bundle\TestFrameworkBundle\Pages\AbstractPageEntity;

/**
 * Class Tag
 *
 * @package Oro\Bundle\TestFrameworkBundle\Pages\Objects
 * {@inheritdoc}
 */
class Tag extends AbstractPageEntity
{
    /** @var  \PHPUnit_Extensions_Selenium2TestCase_Element */
    protected $tagName;

    /** @var  \PHPUnit_Extensions_Selenium2TestCase_Element */
    protected $owner;

    public function __construct($testCase, $redirect = true)
    {
        parent::__construct($testCase, $redirect);
    }

    /**
     * @param bool $new
     *
     * @return $this
     */
    public function init($new = true)
    {
        if ($new) {
            $this->tagName = $this->test->byXPath("//*[@data-ftid='oro_tag_tag_form_name']");
            $this->owner = $this->test->byXPath("//div[starts-with(@id,'s2id_oro_tag_tag_form_owner')]/a");
        }
        return $this;
    }

    /**
     * @param $accountName
     *
     * @return $this
     */
    public function setTagName($accountName)
    {
        $this->tagName->clear();
        $this->tagName->value($accountName);
        return $this;
    }

    public function getTagName()
    {
        return $this->tagName->value();
    }

    public function setOwner($owner)
    {
        $this->owner->click();
        $this->waitForAjax();
        $this->test->byXPath("//div[@id='select2-drop']/div/input")->value($owner);
        $this->waitForAjax();
        $this->assertElementPresent(
            "//div[@id='select2-drop']//div[contains(., '{$owner}')]",
            "Owner autocomplete doesn't return search value"
        );
        $this->test->byXPath("//div[@id='select2-drop']//div[contains(., '{$owner}')]")->click();

        return $this;

    }

    public function getOwner()
    {
        return;
    }

    public function save($button = 'Save')
    {
        return parent::save($button);
    }
}
