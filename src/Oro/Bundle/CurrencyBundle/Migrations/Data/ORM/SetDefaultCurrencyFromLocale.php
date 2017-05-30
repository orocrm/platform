<?php
namespace Oro\Bundle\CurrencyBundle\Migrations\Data\ORM;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfig;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\LocaleBundle\Migrations\Data\ORM\LoadLocalizationData;

class SetDefaultCurrencyFromLocale extends AbstractFixture implements ContainerAwareInterface, DependentFixtureInterface
{
    use ContainerAwareTrait;

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return [
            LoadLocalizationData::class
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager)
    {
        /**@var ConfigManager $configManager **/
        $configManager = $this->container->get('oro_config.global');

        $connection = $this->container->get('doctrine')->getConnection();

        $currencies = $connection->fetchAll('
                                              SELECT 
                                                oro_config_value.text_value
                                              FROM
                                                oro_config_value
                                              WHERE
                                                oro_config_value.name = \'default_currency\'
         ');

        /**
         * If currency already set
         * do nothing
         */
        if (count($currencies)) {
            return;
        }

        $currency = LocaleSettings::getCurrencyByLocale($this->getLocale());

        $configManager->set(
            CurrencyConfig::getConfigKeyByName(CurrencyConfig::KEY_DEFAULT_CURRENCY),
            $currency
        );

        $configManager->flush();
    }

    /**
     * @return string
     */
    protected function getLocale()
    {
        $localeSettings = $this->container->get('oro_locale.settings');

        return $localeSettings->getLocale();
    }
}
