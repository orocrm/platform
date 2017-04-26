<?php
namespace Oro\Bundle\ImportExportBundle\Tests\Functional\Controller;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Oro\Bundle\ImportExportBundle\Async\Topics;
use Oro\Bundle\ImportExportBundle\Job\JobExecutor;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\UserBundle\Entity\User;

class ImportExportControllerTest extends WebTestCase
{
    use MessageQueueExtension;

    protected function setUp()
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->client->useHashNavigation(true);
    }

    public function testShouldSendExportMessageOnInstantExportActionWithDefaultParameters()
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_export_instant', ['processorAlias' => 'oro_account'])
        );

        $this->assertJsonResponseSuccess();

        $organization = $this->getSecurityFacade()->getOrganization();
        $organizationId = $organization ? $organization->getId() : null;

        $this->assertMessageSent(Topics::EXPORT, [
            'jobName' => JobExecutor::JOB_EXPORT_TO_CSV,
            'processorAlias' => 'oro_account',
            'outputFilePrefix' => null,
            'options' => [],
            'userId' => $this->getCurrentUser()->getId(),
            'organizationId' => $organizationId,
        ]);
    }

    public function testShouldSendExportMessageOnInstantExportActionWithPassedParameters()
    {
        $this->client->request(
            'GET',
            $this->getUrl('oro_importexport_export_instant', [
                'processorAlias' => 'oro_account',
                'exportJob' => JobExecutor::JOB_EXPORT_TEMPLATE_TO_CSV,
                'filePrefix' => 'prefix',
                'options' => [
                    'first' => 'first value',
                    'second' => 'second value',
                ]
            ])
        );

        $this->assertJsonResponseSuccess();

        $organization = $this->getSecurityFacade()->getOrganization();
        $organizationId = $organization ? $organization->getId() : null;

        $this->assertMessageSent(Topics::EXPORT, [
            'jobName' => JobExecutor::JOB_EXPORT_TEMPLATE_TO_CSV,
            'processorAlias' => 'oro_account',
            'outputFilePrefix' => 'prefix',
            'options' => [
                'first' => 'first value',
                'second' => 'second value',
            ],
            'userId' => $this->getCurrentUser()->getId(),
            'organizationId' => $organizationId,
        ]);
    }

    public function testImportProcessAction()
    {
        $options = [
            'first' => 'first value',
            'second' => 'second value',
        ];
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_importexport_import_process',
                [
                    'processorAlias' => 'oro_account',
                    'importJob' => JobExecutor::JOB_IMPORT_FROM_CSV,
                    'fileName' => 'test_file',
                    'originFileName' => 'test_file_original',
                    'options' => $options,
                ]
            )
        );

        $this->assertJsonResponseSuccess();

        $this->assertMessageSent(
            Topics::PRE_HTTP_IMPORT,
            [
                'jobName' => JobExecutor::JOB_IMPORT_FROM_CSV,
                'process' => 'import',
                'processorAlias' => 'oro_account',
                'fileName' => 'test_file',
                'originFileName' => 'test_file_original',
                'options' => $options,
                'userId' => $this->getCurrentUser()->getId(),
            ]
        );
    }

    public function testImportValidateAction()
    {
        $options = [
            'first' => 'first value',
            'second' => 'second value',
        ];
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_importexport_import_validate',
                [
                    'processorAlias' => 'oro_account',
                    'importValidateJob' => JobExecutor::JOB_IMPORT_VALIDATION_FROM_CSV,
                    'fileName' => 'test_file',
                    'originFileName' => 'test_file_original',
                    'options' => $options,
                ]
            )
        );

        $this->assertJsonResponseSuccess();

        $this->assertMessageSent(
            Topics::PRE_HTTP_IMPORT,
            [
                'jobName' => JobExecutor::JOB_IMPORT_VALIDATION_FROM_CSV,
                'processorAlias' => 'oro_account',
                'process' => 'import_validation',
                'fileName' => 'test_file',
                'originFileName' => 'test_file_original',
                'options' => $options,
                'userId' => $this->getCurrentUser()->getId(),
            ]
        );
    }

    public function testImportForm()
    {
        $appDir = $this->getContainer()->getParameter('kernel.root_dir');
        $tmpDirName = $this->getContainer()->getParameter('importexport.filesystems_storage');
        $fileDir = __DIR__ . '/Import/fixtures';
        $file = $fileDir . '/testLineEndings.csv';

        $csvFile = new UploadedFile(
            $fileDir . '/testLineEndings.csv',
            'testLineEndings.csv',
            'text/csv'
        );
        $this->assertEquals(
            substr_count(file_get_contents($file), "\r\n"),
            substr_count(file_get_contents($csvFile->getPathname()), "\r\n")
        );
        $this->assertEquals(
            substr_count(file_get_contents($file), "\n"),
            substr_count(file_get_contents($csvFile->getPathname()), "\n")
        );

        $crawler = $this->client->request(
            'GET',
            $this->getUrl(
                'oro_importexport_import_form',
                [
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test',
                    'entity' => User::class,
                ]
            )
        );
        $this->assertHtmlResponseStatusCodeEquals($this->client->getResponse(), 200);

        $uploadFileNode = $crawler->selectButton('Submit');
        $uploadFileForm = $uploadFileNode->form();
        $values = [
            'oro_importexport_import' => [
                '_token' => $uploadFileForm['oro_importexport_import[_token]']->getValue(),
                'processorAlias' => 'oro_user.add_or_replace'
            ],
        ];
        $files = [
            'oro_importexport_import' => [
                'file' => $csvFile
            ]
        ];

        $this->client->request(
            $uploadFileForm->getMethod(),
            $this->getUrl(
                'oro_importexport_import_form',
                [
                    '_widgetContainer' => 'dialog',
                    '_wid' => 'test',
                    'entity' => User::class,
                ]
            ),
            $values,
            $files
        );
        $this->assertJsonResponseSuccess();
        $tmpFiles = glob($appDir . DIRECTORY_SEPARATOR . $tmpDirName . DIRECTORY_SEPARATOR . '*.csv');
        $tmpFile = new File($tmpFiles[count($tmpFiles)-1]);
        $this->assertEquals(
            substr_count(file_get_contents($file), "\n"),
            substr_count(file_get_contents($tmpFile->getPathname()), "\r\n")
        );
        unlink($tmpFile->getPathname());
        unlink($file . '_formatted');
    }

    /**
     * @return object
     */
    private function getSecurityFacade()
    {
        return $this->getContainer()->get('oro_security.security_facade');
    }

    /**
     * @return mixed
     */
    private function getCurrentUser()
    {
        return $this->getContainer()->get('security.token_storage')->getToken()->getUser();
    }

    private function assertJsonResponseSuccess()
    {
        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertNotEmpty($result);
        $this->assertCount(1, $result);
        $this->assertTrue($result['success']);
    }
}
