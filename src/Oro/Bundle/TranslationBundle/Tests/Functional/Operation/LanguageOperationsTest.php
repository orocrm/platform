<?php

namespace Oro\Bundle\TranslationBundle\Tests\Functional\Operation;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;

use Oro\Bundle\ActionBundle\Tests\Functional\ActionTestCase;
use Oro\Bundle\TranslationBundle\Entity\Language;
use Oro\Bundle\TranslationBundle\Helper\LanguageHelper;
use Oro\Bundle\TranslationBundle\Tests\Functional\DataFixtures\LoadLanguages;

/**
 * @dbIsolationPerTest
 */
class LanguageOperationsTest extends ActionTestCase
{
    protected function setUp()
    {
        $this->initClient([], $this->generateBasicAuthHeader());

        $this->loadFixtures(
            [
                LoadLanguages::class,
            ]
        );
        $this->client->disableReboot();
    }

    public function testEnableLanguage()
    {
        /** @var Language $language */
        $language = $this->getReference(LoadLanguages::LANGUAGE1);

        $this->assertFalse($language->isEnabled());
        $this->assertExecuteOperation('oro_translation_language_enable', $language->getId(), Language::class);
        $language = $this->getReference(LoadLanguages::LANGUAGE1);
        $this->assertTrue($language->isEnabled());
    }

    public function testDisableLanguage()
    {
        /** @var Language $language1 */
        $language = $this->getReference(LoadLanguages::LANGUAGE1);
        $language->setEnabled(true);

        $this->assertTrue($language->isEnabled());
        $this->assertExecuteOperation('oro_translation_language_disable', $language->getId(), Language::class);
        $language = $this->getReference(LoadLanguages::LANGUAGE1);
        $this->assertFalse($language->isEnabled());
    }

    public function testAddLanguage()
    {
        /** @var Language $language */
        $language = $this->getReference(LoadLanguages::LANGUAGE1);
        $language->setEnabled(true);

        $this->assertTrue($language->isEnabled());
        $crawler = $this->assertOperationForm('oro_translation_language_add', $language->getId(), Language::class);
        $form = $crawler->selectButton('Add Language')->form([
            'oro_action_operation[language_code]' => 'zu_ZA',
        ]);
        $this->assertOperationFormSubmitted($form, 'Language has been added');
    }

    public function testInstallLanguage()
    {
        /** @var Language $language */
        $language = $this->getReference(LoadLanguages::LANGUAGE1);
        $this->assertNull($language->getInstalledBuildDate());
        $tmpDir = $this->getContainer()->get('oro_translation.service_provider')->getTmpDir('oro-test-trans');
        $tmpDir .= '/' . LoadLanguages::LANGUAGE1;
        $url = $this->getOperationDialogUrl($language, 'oro_translation_language_install');
        $languageHelper = $this->getLanguageHelperMock($tmpDir);
        $languageHelper->expects($this->any())
            ->method('isAvailableInstallTranslates')
            ->willReturn(true);

        $crawler = $this->client->request('GET', $url, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        $form = $crawler->selectButton('Install')->form([
            'oro_action_operation[language_code]' => LoadLanguages::LANGUAGE1,
        ]);

        // temporary file would be removed automatically
        copy(__DIR__ . '/../DataFixtures/Translations/en_CA.zip', $tmpDir . '.zip');

        $crawler = $this->submitOperationForm($form);

        $this->assertContains('Language has been installed', $crawler->html());
        $this->assertNotEmpty($this->getLanguageEntity($language->getId())->getInstalledBuildDate());
    }

    public function testUpdateLanguage()
    {
        /** @var Language $language */
        $language = $this->getReference(LoadLanguages::LANGUAGE2);
        $tmpDir = $this->getContainer()->get('oro_translation.service_provider')->getTmpDir('oro-test-trans');
        $tmpDir .= '/' . LoadLanguages::LANGUAGE2;
        $url = $this->getOperationDialogUrl($language, 'oro_translation_language_update');
        $languageHelper = $this->getLanguageHelperMock($tmpDir);
        $languageHelper->expects($this->any())
            ->method('isAvailableUpdateTranslates')
            ->willReturn(true);

        $crawler = $this->client->request('GET', $url, [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        // temporary file would be removed automatically
        copy(__DIR__ . '/../DataFixtures/Translations/fr_FR.zip', $tmpDir . '.zip');

        $form = $crawler->selectButton('Update')->form([
            'oro_action_operation[language_code]' => LoadLanguages::LANGUAGE2,
        ]);

        $crawler = $this->submitOperationForm($form);

        $this->assertContains('Language has been updated', $crawler->html());
    }

    /**
     * @param Form $form
     * @return Crawler
     */
    private function submitOperationForm(Form $form)
    {
        $token = $this->getContainer()->get('session')->get('_csrf/oro_action_operation');
        $this->getContainer()->get('session')->set('_csrf/oro_action_operation', $token);
        $this->client->followRedirects(true);
        $crawler = $this->client->submit($form);
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        return $crawler;
    }

    /**
     * @param string $tmpDir
     * @return \PHPUnit_Framework_MockObject_MockObject|LanguageHelper
     */
    private function getLanguageHelperMock($tmpDir)
    {
        $languageHelper = $this
            ->getMockBuilder(LanguageHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $languageHelper->expects($this->any())
            ->method('downloadLanguageFile')
            ->willReturn($tmpDir);
        $languageHelper->expects($this->any())
            ->method('getLanguageStatistic')
            ->willReturn(['lastBuildDate' => new \DateTime()]);

        $this->getContainer()->set('oro_translation.helper.language', $languageHelper);

        return $languageHelper;
    }

    /**
     * @param Language $language
     * @param string $operationName
     * @return string
     */
    private function getOperationDialogUrl(Language $language, $operationName)
    {
        return $this->getUrl(
            $this->getOperationDialogRoute(),
            [
                'operationName' => $operationName,
                'entityId' => $language->getId(),
                'entityClass' => Language::class,
                '_widgetContainer' => 'dialog',
                '_wid' => 'test-uuid',
            ]
        );
    }

    /**
     * @param int $id
     * @return Language
     */
    private function getLanguageEntity($id)
    {
        return $this->getContainer()
            ->get('doctrine')
            ->getManagerForClass(Language::class)
            ->getRepository(Language::class)
            ->find($id);
    }
}
