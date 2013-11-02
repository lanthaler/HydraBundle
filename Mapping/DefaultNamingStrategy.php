<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

/**
 * Hydra's default NamingStrategy
 *
 * Uses the classes name (without namespace) as both IRI fragment and short
 * name. The IRI fragment for properties has the "ClassName/propertyName"
 * form and the short name is the underscored, lowercases "property_name".
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com
 */
class DefaultNamingStrategy implements NamingStrategy
{
    /**
     * {@inheritdoc}
     */
    public function classIriFragment($className)
    {
        return $this->classShortName($className);
    }

    /**
     * {@inheritdoc}
     */
    public function propertyIriFragment($className, $propertyName)
    {
        $property = preg_replace_callback(
            '/_(.)/',
            function ($match) {
                return strtoupper($match[1]);
            },
            $propertyName
        );

        // Remove "get" and "set" prefixes
        if (0 === strncmp($property, 'get', 3) || 0 === strncmp($property, 'set', 3)) {
            $property = strtolower($property[3]) . substr($property, 4);

            // ... and the Iri suffix of such methods
            if ('Iri' === substr($property, -3)) {
                $property = substr($property, 0, -3);
            }
        }

        // The # is already included in the classIriFragment
        return $this->classIriFragment($className) . '/' . $property;
    }

    /**
     * {@inheritdoc}
     */
    public function classShortName($className)
    {
        if (strpos($className, '\\') !== false) {
            return substr($className, strrpos($className, '\\') + 1);
        }

        return $className;
    }

    /**
     * {@inheritdoc}
     */
    public function propertyShortName($className, $propertyName)
    {
        $property = strtolower(
            preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $propertyName)
        );

        // Remove "get" and "set" prefixes
        if (0 === strncmp($property, 'get_', 3) || 0 === strncmp($property, 'set_', 3)) {
            $property = substr($property, 4);

            // ... and the Iri suffix of such methods
            if ('_iri' === substr($property, -4)) {
                $property = substr($property, 0, -4);
            }
        }

        return $property;
    }
}
