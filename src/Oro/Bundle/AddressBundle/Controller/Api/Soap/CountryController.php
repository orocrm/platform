<?php

namespace Oro\Bundle\AddressBundle\Controller\Api\Soap;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;
use Doctrine\Common\Persistence\ObjectManager;
use Oro\Bundle\AddressBundle\Entity\Address;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * TODO: Discuss ACL impl.
 */
class CountryController extends ContainerAware
{
    /**
     * @Soap\Method("getCountries")
     * @Soap\Result(phpType = "Oro\Bundle\AddressBundle\Entity\Country[]")
     * AclAncestor("oro_address")
     */
    public function cgetAction()
    {
        return $this->getManager()->getRepository('OroAddressBundle:Country')->findAll();
    }

    /**
     * @Soap\Method("getCountry")
     * @Soap\Param("iso2Code", phpType = "string")
     * @Soap\Result(phpType = "Oro\Bundle\AddressBundle\Entity\Country")
     * AclAncestor("oro_address")
     */
    public function getAction($iso2Code)
    {
        return $this->getEntity('OroAddressBundle:Country', $iso2Code);
    }

    /**
     * Shortcut to get entity
     *
     * @param string $repo
     * @param int|string $id
     * @throws \SoapFault
     * @return Address
     */
    protected function getEntity($repo, $id)
    {
        $entity = $this->getManager()->find($repo, $id);

        if (!$entity) {
            throw new \SoapFault('NOT_FOUND', sprintf('Record #%u can not be found', $id));
        }

        return $entity;
    }

    /**
     * @return ObjectManager
     */
    protected function getManager()
    {
        return $this->container->get('doctrine.orm.entity_manager');
    }
}
