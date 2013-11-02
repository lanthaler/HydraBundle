<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

use Symfony\Component\Routing\Route as SfRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * A Hydra Class definition
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class ClassMetadata
{
    /**
     * @var string The fully-qualified name of this class
     */
    private $name;

    /**
     * @var string The name that should be used when serializing this class
     */
    private $exposeAs;

    /**
     * @var string The IRI used to identify this class
     */
    private $iri;

    /**
     * @var string The title of the description of this class
     */
    private $title;

    /**
     * @var string A description of this class
     */
    private $description;

    /**
     * @var SfRoute The route to create IRIs identifying instances of this class
     */
    private $route;

    /**
     * @var array The properties known to be supported by instances of this class
     */
    private $properties;

    /**
     * @var array The operations known to be supported by instances of this class
     */
    private $operations = array();

    /**
     * Constructor
     *
     * @param string $name The fully-qualified name of the class being
     *                     documented.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the fully-qualified name of this class
     *
     * @return string The fully-qualified name of this class.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name to be used when serializing this class
     *
     * @return string The name to be used when serializing this class.
     *
     * @return ClassMetadata $this
     */
    public function setExposeAs($exposeAs)
    {
        $this->exposeAs = $exposeAs;

        return $this;
    }

    /**
     * Gets the name to be used when serializing this class
     *
     * @return string The name to be used when serializing this class.
     */
    public function getExposeAs()
    {
        return $this->exposeAs;
    }

    /**
     * Sets the IRI identifying this class
     *
     * @return string The IRI identifying this class.
     *
     * @return ClassMetadata $this
     */
    public function setIri($iri)
    {
        $this->iri = $iri;

        return $this;
    }

    /**
     * Gets the IRI identifying this class
     *
     * @return string The IRI identifying this class.
     */
    public function getIri()
    {
        return $this->iri;
    }

    /**
     * Does this class definition represent a reference to an external
     * definition?
     *
     * An external definition can be used but not modified or further
     * annotated as it is not under the control of this system.
     *
     * @return boolean True if this class definition represents an external
     *                 reference, false otherwise.
     */
    public function isExternalReference()
    {
        return strpos($this->iri, ':') !== false;
    }

    /**
     * Sets the title of the description of this class
     *
     * @param string $title The title of the description of this class.
     *
     * @return ClassMetadata $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the title of the description of this class
     *
     * @return string The title of the description of this class.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the description of this class
     *
     * @param string $description The description of this class.
     *
     * @return ClassMetadata $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the description of this class
     *
     * @return string The description of this class.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the route to create IRIs identifying instances of this class
     *
     * @param OperationDefinition $route The route to create IRIs identifying
     *                                   instances of this class.
     *
     * @return ClassMetadata $this
     */
    public function setRoute(OperationDefinition $route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Gets the route to create IRIs identifying instances of this class
     *
     * @return OperationDefinition The route to create IRIs identifying
     *                             instances of this class.
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Sets the properties known to be supported by instances of this class
     *
     * @param array $properties The properties known to be supported by
     *                          instances of this class.
     *
     * @return ClassMetadata $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Gets the properties known to be supported by instances of this class
     *
     * @return array The properties known to be supported by instances of
     *               this class.
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Sets the operations known to be supported by instances of this class
     *
     * @param array $operations The operations known to be supported by
     *                          instances of this class.
     *
     * @return ClassMetadata $this
     */
    public function setOperations(array $operations)
    {
        $this->operations = $operations;

        return $this;
    }

    /**
     * Adds an operation known to be supported by instances of this class
     *
     * @param array $operations The operation known to be supported by
     *                          instances of this class.
     *
     * @return PropertyMetadata $this
     */
    public function addOperation(OperationDefinition $operation)
    {
        if (false === $this->supportsOperation($operation->getName())) {
            $this->operations[] = $operation;
        }

        return $this;
    }

    /**
     * Gets the operations known to be supported by instances of this class
     *
     * @return array The operations known to be supported by instances of
     *               this class.
     */
    public function getOperations()
    {
        return $this->operations;
    }


    /**
     * Checks whether a specific operation is known to be supported
     *
     * @param string $operationName The name of the operation.
     *
     * @return boolean True if the operation is supported, false otherwise.
     */
    public function supportsOperation($operationName)
    {
        foreach ($this->operations as $operation) {
            if ($operation->getName() === $operationName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the specified property to the specified value on the given entity
     *
     * @param object $entity
     * @param string $property
     * @param mixed $value
     */
    public function setPropertyValue($entity, $property, $value)
    {
        $this->reflFields[$property]->setValue($entity, $value);
    }

    /**
     * Gets the specified property's value of the given entity
     *
     * @param object $entity
     * @param string $property
     */
    public function getPropertyValue($entity, $property)
    {
        return $this->reflFields[$property]->getValue($entity);
    }

    /**
     * Determines which fields get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     * That means any metadata properties that are not set or empty or simply have
     * their default value are NOT serialized.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     *      - reflClass (ReflectionClass)
     *      - reflFields (ReflectionProperty array)
     *
     * @return array The names of all the fields that should be serialized.
     */
    public function __sleep()
    {
        // This metadata is always serialized/cached.
        $serialized = array(
            'associationMappings',
            'columnNames', //TODO: Not really needed. Can use fieldMappings[$fieldName]['columnName']
            'fieldMappings',
            'fieldNames',
            'identifier',
            'isIdentifierComposite', // TODO: REMOVE
            'name',
            'namespace', // TODO: REMOVE
            'table',
            'rootEntityName',
            'idGenerator', //TODO: Does not really need to be serialized. Could be moved to runtime.
        );

        // The rest of the metadata is only serialized if necessary.
        if ($this->changeTrackingPolicy != self::CHANGETRACKING_DEFERRED_IMPLICIT) {
            $serialized[] = 'changeTrackingPolicy';
        }

        if ($this->customRepositoryClassName) {
            $serialized[] = 'customRepositoryClassName';
        }

        if ($this->inheritanceType != self::INHERITANCE_TYPE_NONE) {
            $serialized[] = 'inheritanceType';
            $serialized[] = 'discriminatorColumn';
            $serialized[] = 'discriminatorValue';
            $serialized[] = 'discriminatorMap';
            $serialized[] = 'parentClasses';
            $serialized[] = 'subClasses';
        }

        if ($this->generatorType != self::GENERATOR_TYPE_NONE) {
            $serialized[] = 'generatorType';
            if ($this->generatorType == self::GENERATOR_TYPE_SEQUENCE) {
                $serialized[] = 'sequenceGeneratorDefinition';
            }
        }

        if ($this->isMappedSuperclass) {
            $serialized[] = 'isMappedSuperclass';
        }

        if ($this->containsForeignIdentifier) {
            $serialized[] = 'containsForeignIdentifier';
        }

        if ($this->isVersioned) {
            $serialized[] = 'isVersioned';
            $serialized[] = 'versionField';
        }

        if ($this->lifecycleCallbacks) {
            $serialized[] = 'lifecycleCallbacks';
        }

        if ($this->namedQueries) {
            $serialized[] = 'namedQueries';
        }

        if ($this->namedNativeQueries) {
            $serialized[] = 'namedNativeQueries';
        }

        if ($this->sqlResultSetMappings) {
            $serialized[] = 'sqlResultSetMappings';
        }

        if ($this->isReadOnly) {
            $serialized[] = 'isReadOnly';
        }

        if ($this->customGeneratorDefinition) {
            $serialized[] = "customGeneratorDefinition";
        }

        return $serialized;
    }
}
