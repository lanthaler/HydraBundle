<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

use ML\HydraBundle\Mapping\NamingStrategy;
use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Util\ClassUtils;

/**
 * ClassMetadataFactory
 *
 * The ClassMetadataFactory is used to create ClassMetadata objects that
 * contain all the metadata mapping information of a class which describes
 * how a class is mapped to Hydra.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class ClassMetadataFactory
{
    /**
     * @var \Doctrine\Common\Persistence\Mapping\Driver\MappingDriver
     */
    private $driver;

    /**
     * @var \Doctrine\Common\Cache\Cache
     */
    private $cacheDriver;

    /**
     * @var string Salt used for caching.
     */
    protected $cacheSalt = "\$HYDRA-CLASSMETADATA";

    /**
     * @var NamingStrategy
     */
    private $namingStrategy;

    /**
     * @var array
     */
    private $loadedMetadata = array();

    /**
     * Constructor
     *
     * object $driver      The mapping driver.
     * Cache  $cacheDriver Cache driver used by the factory to cache
     *                     ClassMetadata instances.
     */
    public function __construct($driver, Cache $cacheDriver, NamingStrategy $namingStrategy)
    {
        $this->driver = $driver;
        $this->cacheDriver = $cacheDriver;
        $this->namingStrategy = $namingStrategy;
    }

    /**
     * Return the mapping driver.
     *
     * @return object The mapping driver.
     */
    protected function getDriver()
    {
        return $this->driver;
    }

    /**
     * Sets the cache driver used by the factory to cache ClassMetadata
     * instances.
     *
     * @param Doctrine\Common\Cache\Cache $cacheDriver
     */
    public function setCacheDriver(Cache $cacheDriver = null)
    {
        $this->cacheDriver = $cacheDriver;
    }

    /**
     * Gets the cache driver used by the factory to cache ClassMetadata instances.
     *
     * @return Doctrine\Common\Cache\Cache
     */
    public function getCacheDriver()
    {
        return $this->cacheDriver;
    }

    /**
     * Forces the factory to load the metadata of all classes known to the underlying
     * mapping driver.
     *
     * @return array The ClassMetadata instances of all mapped classes.
     */
    public function getAllMetadata()
    {
        // FIXXME Should this be implemented here or in the driver (chain)?
        $metadata = array();
        foreach ($this->driver->getAllClassNames() as $className) {
            $metadata[] = $this->getMetadataFor($className);
        }

        $this->validate($metadata);

        return $metadata;
    }

    /**
     * Gets the class metadata for the specified class
     *
     * @param string $className The name of the class.
     * @return ClassMetadata The metadata.
     */
    public function getMetadataFor($className)
    {
        if (isset($this->loadedMetadata[$className])) {
            return $this->loadedMetadata[$className];
        }

        $realClassName = ClassUtils::getRealClass($className);

        if (isset($this->loadedMetadata[$realClassName])) {
            // We do not have the alias name in the map, include it
            $this->loadedMetadata[$className] = $this->loadedMetadata[$realClassName];

            return $this->loadedMetadata[$realClassName];
        }

        if ($this->cacheDriver) {
            if (($cached = $this->cacheDriver->fetch($realClassName . $this->cacheSalt)) !== false) {
                $this->loadedMetadata[$realClassName] = $cached;
            } else {
                $this->cacheDriver->save(
                    $realClassName . $this->cacheSalt, $this->loadMetadata($realClassName), null
                );
            }
        } else {
            $this->loadMetadata($realClassName);
        }

        if ($className != $realClassName) {
            // We do not have the alias name in the map, include it
            $this->loadedMetadata[$className] = $this->loadedMetadata[$realClassName];
        }

        return $this->loadedMetadata[$className];
    }

    /**
     * Loads the metadata of the specified class
     *
     * @param string $nameName The name of the class for which the metadata
     *                         should be loaded.
     *
     * @return ClassMetadata
     */
    protected function loadMetadata($className)
    {
        if (false === isset($this->loadedMetadata[$className])) {
            if (null === ($class = $this->driver->loadMetadataForClass($className))) {
                // FIXXME Improve this
                throw new \Exception("Can't load metadata for $className");
            }

            $this->completeMetadata($class);
            $this->loadedMetadata[$className] = $class;
        }

        return $this->loadedMetadata[$className];
    }

    /**
     * Completes the metadata by setting missing fields that can be inferred
     * by other fields
     *
     * Furthermore, the type of properties which is returned as string by
     * the metadata driver is replaced with a ClassMetadata instance.
     *
     * @param ClassMetadata $class The metadata to complete
     */
    protected function completeMetadata(ClassMetadata $class)
    {
        $className = $class->getName();

        if (null === $class->getIri()) {
            $class->setIri($this->namingStrategy->classIriFragment($className));
        }

        if (null === $class->getExposeAs()) {
            $class->setExposeAs($this->namingStrategy->classShortName($className));
        }


        // If no title has been set for this class, use it's short name
        if (null === $class->getTitle()) {
            $class->setTitle($this->namingStrategy->classShortName($className));
        }

        foreach ($class->getProperties() as $property) {
            $propertyName = $property->getName();

            if (null === $property->getIri()) {
                $property->setIri(
                    $this->namingStrategy->propertyIriFragment($className, $propertyName)
                );
            }

            if (null === $property->getExposeAs()) {
                $property->setExposeAs(
                    $this->namingStrategy->propertyShortName($className, $propertyName)
                );
            }

            // If no title has been set for this property, use it's short name
            if (null === $property->getTitle()) {
                $property->setTitle(
                    $this->namingStrategy->propertyShortName($className, $propertyName)
                );
            }
        }
    }

    /**
     * Completes the metadata of operations by replacing class references
     *
     * @param  [type] $operations   [description]
     *
     * @return [type] [description]
     */
    private function completeOperationsMetadata($operations)
    {
        // FIXXME Implement this
    }

    /**
     * Validates whether the metadata is consistent and complete
     *
     * @param  array<ClassMetadata> $metadata The metadata to validate
     *
     * @return boolean True if the metadata is valid, false otherwise
     */
    private function validate($metadata)
    {
        // FIXXME Implement this

        // Check operations expects/returns, properties' type

        // Verify that route's return type corresponds with the property's type

        // Verify that all operations have the same IRI template

        // Look for collisions: IRI, exposeAs
    }
}
