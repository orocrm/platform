<?php

namespace Oro\Bundle\GridBundle\Datagrid;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;

interface QueryConverterInterface
{
    /**
     * Parses a YAML string to a QueryBuilder object.
     *
     * @param  string|array      $value A YAML string or structured associative array
     * @param  EntityManager     $em    Entity manager used to create QueryBuilder
     * @return QueryBuilder
     * @throws \RuntimeException If the YAML is not valid
     */
    public function parse($value, EntityManager $em);

    /**
     * Dumps a QueryBuilder object to YAML.
     *
     * @param  QueryBuilder $input
     * @return string       The YAML representation of the PHP value
     */
    public function dump(QueryBuilder $input);
}
