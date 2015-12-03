<?php

namespace Oro\Bundle\AddressBundle\Controller\Api\Rest;

use Doctrine\ORM\Query;
use FOS\Rest\Util\Codes;
use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Oro\Bundle\AddressBundle\Entity\Country;
use Oro\Bundle\AddressBundle\Entity\Repository\RegionRepository;

use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Symfony\Component\HttpFoundation\Response;

/**
 * @RouteResource("country/regions")
 * @NamePrefix("oro_api_country_")
 * TODO: Discuss ACL impl.
 */
class CountryRegionsController extends FOSRestController
{
    /**
     * REST GET regions by country
     *
     * @param Country $country
     *
     * @ApiDoc(
     *  description="Get regions by country id",
     *  resource=true
     * )
     * AclAncestor("oro_address")
     * @return Response
     */
    public function getAction(Country $country = null)
    {
        if (!$country) {
            return $this->handleView(
                $this->view(null, Codes::HTTP_NOT_FOUND)
            );
        }

        /** @var $regionRepository RegionRepository */
        $regionRepository = $this->getDoctrine()->getRepository('OroAddressBundle:Region');
        $regions = $regionRepository->getCountryRegions($country);

        return $this->handleView(
            $this->view($regions, Codes::HTTP_OK)
        );
    }
}
