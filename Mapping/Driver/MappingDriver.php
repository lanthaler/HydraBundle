<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping\Driver;

/**
 * The Hydra AnnotationDriver
 */
interface MappingDriver
{
    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames();

    /**
     * Is the class marked as being exposed?
     *
     * Whether the class with the specified name is exposed. Only exposed
     * classes should have their metadata loaded.
     *
     * A class is exposed if it is annotated with an
     * {@see Hydra\Mapping\Expose} annotation.
     *
     * @param string $className
     * @return boolean
     */
    public function isExposed($className);

    /**
     * Loads the metadata of the specified class
     *
     * @param string $nameName The name of the class for which the metadata
     *                         should be loaded.
     *
     * @return ML\HydraBundle\Mapping\ClassMetadata
     */
    public function loadMetadataForClass($className);
}
