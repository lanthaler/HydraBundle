<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping\Driver;

use ML\HydraBundle\Mapping\ClassMetadata;

/**
 * DriverChain
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class DriverChain
{
    /**
     * @var array
     */
    private $drivers = array();

    /**
     * Add a nested driver.
     *
     * @param MappingDriver $nestedDriver
     * @param string $namespace
     */
    public function addDriver(MappingDriver $nestedDriver, $namespace)
    {
        $this->drivers[$namespace] = $nestedDriver;
    }

    /**
     * Get the array of nested drivers.
     *
     * @return array $drivers
     */
    public function getDrivers()
    {
        return $this->drivers;
    }

    /**
     * Loads the metadata for the specified class
     *
     * @param string $className The name of the class.
     *
     * @return ClassMetadata|null The metadata for the specified class or null.
     */
    public function loadMetadataForClass($className)
    {
        foreach ($this->drivers as $namespace => $driver) {
            if (strpos($className, $namespace) === 0) {
                return $driver->loadMetadataForClass($className);
            }
        }

        return null;
    }

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return array The names of all mapped classes known to this driver.
     */
    public function getAllClassNames()
    {
        $classNames = array();
        $driverClasses = array();

        foreach ($this->drivers as $namespace => $driver) {
            $oid = spl_object_hash($driver);

            if (!isset($driverClasses[$oid])) {
                $driverClasses[$oid] = $driver->getAllClassNames();
            }

            foreach ($driverClasses[$oid] as $className) {
                if (strpos($className, $namespace) === 0) {
                    $classNames[$className] = true;
                }
            }
        }

        return array_keys($classNames);
    }
}
