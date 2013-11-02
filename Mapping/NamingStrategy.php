<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

/**
 * NamingStrategy interface
 *
 * The interface used to convert class names, properties and methods to IRIs
 * and short names as used in, e.g., JSON-LD.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com
 */
interface NamingStrategy
{
    /**
     * Get a class' IRI fragment
     *
     * @param string $className The fully-qualified class name
     */
    public function classIriFragment($className);

    /**
     * Get a property's IRI fragment
     *
     * @param string $className    The fully-qualified class name
     * @param string $propertyName The property name (might be a method name)
     */
    public function propertyIriFragment($className, $propertyName);

    /**
     * Get a class' short name
     *
     * @param string $className The fully-qualified class name
     */
    public function classShortName($className);

    /**
     * Get a property's short name
     *
     * @param string $className    The fully-qualified class name
     * @param string $propertyName The property name (might be a method name)
     */
    public function propertyShortName($className, $propertyName);
}
