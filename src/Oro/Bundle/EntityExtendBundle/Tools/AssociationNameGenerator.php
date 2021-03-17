<?php
declare(strict_types=1);

namespace Oro\Bundle\EntityExtendBundle\Tools;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\Rules\English\InflectorFactory;

/**
 * Provides methods to generate method names for extended relations.
 */
class AssociationNameGenerator
{
    private static ?Inflector $inflector = null;

    /**
     * Converts a string into a "class-name-like" name, e.g. 'first_name' to 'FirstName'.
     *
     * @param string $string
     *
     * @return string
     */
    public static function classify($string)
    {
        if (null === self::$inflector) {
            self::$inflector = (new InflectorFactory())->build();
        }
        return self::$inflector->classify(null === $string ? '' : $string);
    }

    /**
     * Generates method name to checks if an entity can be associated with another entity
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateSupportTargetMethodName($associationKind)
    {
        return sprintf('support%sTarget', self::classify($associationKind));
    }

    /**
     * Generates method name to get associated entity
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateGetTargetMethodName($associationKind)
    {
        return sprintf('get%sTarget', self::classify($associationKind));
    }

    /**
     * Generates method name to get associated entities
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateGetTargetsMethodName($associationKind)
    {
        return sprintf('get%sTargets', self::classify($associationKind));
    }

    /**
     * Generates method name to set association to another entity
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateSetTargetMethodName($associationKind)
    {
        return sprintf('set%sTarget', self::classify($associationKind));
    }

    /**
     * Generates method name to reset associations
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateResetTargetsMethodName($associationKind)
    {
        return sprintf('reset%sTargets', self::classify($associationKind));
    }

    /**
     * Generates method name to add association to another entity
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateAddTargetMethodName($associationKind)
    {
        return sprintf('add%sTarget', self::classify($associationKind));
    }

    /**
     * Generates method name to check if entity is associated with another entity
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateHasTargetMethodName($associationKind)
    {
        return sprintf('has%sTarget', self::classify($associationKind));
    }

    /**
     * Generates method name to remove association
     *
     * @param string|null $associationKind The association type or NULL for unclassified (default) association
     *
     * @return string
     */
    public static function generateRemoveTargetMethodName($associationKind)
    {
        return sprintf('remove%sTarget', self::classify($associationKind));
    }
}
