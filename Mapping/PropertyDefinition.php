<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

use Symfony\Component\PropertyAccess\StringUtil;

/**
 * A Hydra Property definition
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class PropertyDefinition
{
    /**
     * Constant used to specify that the getter/setter is a property
     */
    const GETTER_SETTER_PROPERTY = 1;

    /**
     * Constant used to specify that the getter/setter is a method
     */
    const GETTER_SETTER_METHOD = 2;

    /**
     * @var string The fully-qualified name of the class the property belongs to
     */
    private $class;

    /**
     * @var string The name of the property
     */
    private $name;

    /**
     * @var string The name that should be used when serializing this property
     */
    private $exposeAs;

    /**
     * @var string The IRI used to identify this property
     */
    private $iri;

    /**
     * @var string The title of the description of this property
     */
    private $title;

    /**
     * @var string A description of this property
     */
    private $description;

    /**
     * @var string The value type of this property (either a primitive type
     *             or a fully-qualified class name)
     */
    private $type;

    /**
     * @var boolean Is this a required property?
     */
    private $required;

    /**
     * @var boolean Is this a read-only property?
     */
    private $readOnly;

    /**
     * @var boolean Is this a write-only property?
     */
    private $writeOnly;

    /**
     * @var string The route to create IRIs identifying instances of this property
     */
    private $route;

    /**
     * @var array Mapping of route variables to members and methods (property paths)
     */
    private $routeVariableMappings;

    /**
     * @var array The operations known to be supported by instances of this property
     */
    private $operations = array();

    /**
     * @var string The name of the getter to get a value of this property from an entity
     */
    private $getter;

    /**
     * Type of the getter
     *
     * One of {@link PropertyDefinition::GETTER_SETTER_PROPERTY} or
     * {@link PropertyDefinition::GETTER_SETTER_METHOD}.
     *
     * @var integer
     */
    private $getterType;

    /**
     * @var string The name of the setter to set a value of this property on an entity
     */
    private $setter;

    /**
     * Type of the setter
     *
     * One of {@link PropertyDefinition::GETTER_SETTER_PROPERTY} or
     * {@link PropertyDefinition::GETTER_SETTER_METHOD}.
     *
     */
    private $setterType;

    /**
     * @var string The name of the adder/remove to add or remove entries from an array value
     */
    private $adderRemover;

    /**
     * Constructor
     *
     * @param string $class The fully-qualified name of the class the
     *                      property belongs to.
     * @param string $name  The name of the property being documented.
     */
    public function __construct($class, $name)
    {
        $this->class = $class;
        $this->name = $name;

        $this->findGetter();
        $this->findSetter();
        $this->findAdderAndRemover();

        $this->readOnly = false;
        $this->writeOnly = false;

        if ((null === $this->getter) && ((null !== $this->setter) || (null !== $this->adderRemover))) {
            $this->writeOnly = true;
        } elseif ((null !== $this->getter) && (null === $this->setter) && (null === $this->adderRemover)) {
            $this->readOnly = true;
        }
    }

    /**
     * Gets the fully-qualified name of the class this property belongs to
     *
     * @return string The fully-qualified name of the class this property belongs to.
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Gets the name of this property
     *
     * @return string The the name of this property.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name to be used when serializing this property
     *
     * @return string The name to be used when serializing this property.
     *
     * @return PropertyDefinition $this
     */
    public function setExposeAs($exposeAs)
    {
        $this->exposeAs = $exposeAs;

        return $this;
    }

    /**
     * Gets the name to be used when serializing this property
     *
     * @return string The name to be used when serializing this property.
     */
    public function getExposeAs()
    {
        return $this->exposeAs;
    }

    /**
     * Sets the IRI identifying this property
     *
     * @return string The IRI identifying this property.
     *
     * @return PropertyDefinition $this
     */
    public function setIri($iri)
    {
        $this->iri = $iri;

        return $this;
    }

    /**
     * Gets the IRI identifying this property
     *
     * @return string The IRI identifying this property.
     */
    public function getIri()
    {
        return $this->iri;
    }

    /**
     * Does this property definition represent a reference to an external
     * definition?
     *
     * An external definition can be used but not modified or further
     * annotated as it is not under the control of this system.
     *
     * @return boolean True if this property definition represents an
     *                 external reference, false otherwise.
     */
    public function isExternalReference()
    {
        return strpos($this->iri, ':') !== false;
    }

    /**
     * Sets the title of the description of this property
     *
     * @param string $title The title of the description of this property.
     *
     * @return PropertyMetadata $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the title of the description of this property
     *
     * @return string The title of the description of this property.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the description of this property
     *
     * @param string $description The description of this property.
     *
     * @return PropertyMetadata $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the description of this property
     *
     * @return string The description of this property.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the type of this property
     *
     * This is either a fully-qualified class name, a primitive type, or
     * an absolute IRI. Absolute IRIs can be detected by looking for a colon.
     *
     * @param string $type The type of this property.
     *
     * @return PropertyMetadata $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the type of this property
     *
     * This is either a fully-qualified class name, a primitive type, or
     * an absolute IRI. Absolute IRIs can be detected by looking for a colon.
     *
     * @return string The type of this property.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Is the property required?
     *
     * @param boolean $required Is this a required property
     *
     * @return PropertyMetadata $this
     */
    public function setRequired($required)
    {
        $this->required = $required;

        return $this;
    }

    /**
     * Is the property required?
     *
     * @return boolean True if the property is required; otherwise false.
     */
    public function getRequired()
    {
        return $this->required;
    }

    /**
     * Is the property read-only?
     *
     * @param boolean $readOnly Is this a read-only property
     *
     * @return PropertyMetadata $this
     */
    public function setReadOnly($readOnly)
    {
        $this->readOnly = (boolean)$readOnly;

        return $this;
    }

    /**
     * Is the property read-only?
     *
     * @return boolean True if the property is read-only; otherwise false.
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * Is the property write-only?
     *
     * @param boolean $writeOnly Is this a write-only property
     *
     * @return PropertyMetadata $this
     */
    public function setWriteOnly($writeOnly)
    {
        $this->writeOnly = (boolean)$writeOnly;

        return $this;
    }

    /**
     * Is the property write-only?
     *
     * @return boolean True if the property is write-only; otherwise false.
     */
    public function isWriteOnly()
    {
        return $this->writeOnly;
    }

    /**
     * Sets the route to create IRIs identifying instances of this property
     *
     * @param string $route The route to create IRIs identifying instances
     *                      of this property.
     *
     * @return PropertyMetadata $this
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Gets the route to create IRIs identifying instances of this property
     *
     * @return string The route to create IRIs identifying instances of this
     *                property.
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Sets the operations known to be supported by instances of this property
     *
     * @param array $operations The operations known to be supported by
     *                          instances of this property.
     *
     * @return PropertyMetadata $this
     */
    public function setOperations(array $operations)
    {
        $this->operations = $operations;

        return $this;
    }

    /**
     * Adds an operation known to be supported by instances of this property
     *
     * @param array $operations The operation known to be supported by
     *                          instances of this property.
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
     * Gets the operations known to be supported by instances of this property
     *
     * @return array The operations known to be supported by instances of
     *               this property.
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
     * Has a setter be found for this property
     *
     * @return boolean True if a setter has been found, false otherwise.
     */
    public function hasSetter()
    {
        return (null !== $this->setter);
    }

    /**
     * Has a getter be found for this property
     *
     * @return boolean True if a getter has been found, false otherwise.
     */
    public function hasGetter()
    {
        return (null !== $this->setter);
    }

    /**
     * Sets this property on the given entity to the specified value
     *
     * @param object $entity The entity.
     * @param mixed $value   The value.
     *
     * @throws \Exception If no setter has been found or the entity is of
     *                    the wrong type.
     */
    public function setValue($entity, $value)
    {
        if (false === ($entity instanceof $this->class)) {
            // FIXXME Improve this message
            throw new \Exception(
                "Can't set the entity's {$this->name} property as the entity is not an instance of {$this->class}."
            );
        }

        if (!is_array($value) && !($value instanceof \Traversable)) {
            if ((null !== $this->adderRemover) && (null !== $this->getter)) {
                // Use iterator_to_array() instead of clone in order to prevent side effects
                // see https://github.com/symfony/symfony/issues/4670
                $itemsToAdd = is_object($value) ? iterator_to_array($value) : $value;
                $itemToRemove = array();
                $previousValue = $this->getValue($entity);

                if (is_array($previousValue) || $previousValue instanceof \Traversable) {
                    foreach ($previousValue as $previousItem) {
                        foreach ($value as $key => $item) {
                            if ($item === $previousItem) {
                                // Item found, don't add
                                unset($itemsToAdd[$key]);

                                // Next $previousItem
                                continue 2;
                            }
                        }

                        // Item not found, add to remove list
                        $itemToRemove[] = $previousItem;
                    }
                }

                foreach ($itemToRemove as $item) {
                    call_user_func(array($entity, 'remove' . $this->adderRemover), $item);
                }

                foreach ($itemsToAdd as $item) {
                    call_user_func(array($entity, 'add' . $this->adderRemover), $item);
                }

                return;
            }
        }

        if (null === $this->setter) {
            // FIXXME Improve this message
            throw new \Exception(
                "Can't set the entity's {$this->name} property as no setter has been found."
            );
        }

        if (self::GETTER_SETTER_METHOD === $this->setterType) {
            return $entity->{$this->setter}($value);
        } else {
            return $entity->{$this->setter} = $value;
        }
    }

    /**
     * Gets this property's value of the given entity
     *
     * @param object $entity The entity.
     *
     * @return mixed The property's value.
     */
    public function getValue($entity)
    {
        if (null === $this->getter) {
            // FIXXME Improve this message
            throw new \Exception(
                "Can't get the entity's {$this->name} property as no getter has been found."
            );
        } elseif (false === ($entity instanceof $this->class)) {
            // FIXXME Improve this message
            throw new \Exception(
                "Can't get the entity's {$this->name} property as the entity is not an instance of {$this->class}."
            );
        }

        if (self::GETTER_SETTER_METHOD === $this->getterType) {
            return $entity->{$this->getter}();
        } else {
            return $entity->{$this->getter};
        }
    }

    /**
     * Try to find the getter associated to this property
     *
     * Simplified version of {@link Symfony\Component\PropertyAccess\PropertyAccessor}.
     */
    private function findGetter()
    {
        $reflClass = new \ReflectionClass($this->class);
        $camelProp = $this->camelize($this->name);

        // Try to find a getter
        $getter = 'get'.$camelProp;
        $isser = 'is'.$camelProp;
        $hasser = 'has'.$camelProp;
        $classHasProperty = $reflClass->hasProperty($this->name);

        if ($reflClass->hasMethod($this->name) && $reflClass->getMethod($this->name)->isPublic()) {
            $this->getter = $this->name;
            $this->getterType = self::GETTER_SETTER_METHOD;
        } elseif ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            $this->getter = $getter;
            $this->getterType = self::GETTER_SETTER_METHOD;
        } elseif ($reflClass->hasMethod($isser) && $reflClass->getMethod($isser)->isPublic()) {
            $this->getter = $isser;
            $this->getterType = self::GETTER_SETTER_METHOD;
        } elseif ($reflClass->hasMethod($hasser) && $reflClass->getMethod($hasser)->isPublic()) {
            $this->getter = $hasser;
            $this->getterType = self::GETTER_SETTER_METHOD;
        } elseif (($reflClass->hasMethod('__get') && $reflClass->getMethod('__get')->isPublic()) ||
                  ($classHasProperty && $reflClass->getProperty($this->name)->isPublic())) {
            $this->getter = $this->name;
            $this->getterType = self::GETTER_SETTER_PROPERTY;
        // } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
        //     // we call the getter and hope the __call do the job
        //     $result[self::VALUE] = $object->$getter();
        }
    }

    /**
     * Camelizes a given string
     *
     * Turns something like property_name into propertyName.
     *
     * Copied from {@link Symfony\Component\PropertyAccess\PropertyAccessor::camelize()}.
     *
     * @param string $string Some string.
     *
     * @return string The camelized version of the string.
     */
    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $string);
    }

    /**
     * Try to find the setter associated to this property
     *
     * Simplified version of {@link Symfony\Component\PropertyAccess\PropertyAccessor}.
     */
    private function findSetter()
    {
        $reflClass = new \ReflectionClass($this->class);

        $setter = 'set' . $this->camelize($this->name);
        $classHasProperty = $reflClass->hasProperty($this->name);

        if ($reflClass->hasMethod($setter) && $reflClass->getMethod($setter)->isPublic()) {
            $this->setter = $setter;
            $this->setterType = self::GETTER_SETTER_METHOD;
        } elseif ((0 === strpos($this->name, 'set')) && $reflClass->hasMethod($this->name) && $reflClass->getMethod($this->name)->isPublic()) {
            $this->setter = $this->name;
            $this->setterType = self::GETTER_SETTER_METHOD;
        } elseif (($reflClass->hasMethod('__set') && $reflClass->getMethod('__set')->isPublic()) ||
                  ($classHasProperty && $reflClass->getProperty($this->name)->isPublic())) {
            $this->setter = $this->name;
            $this->setterType = self::GETTER_SETTER_PROPERTY;
        // } elseif ($this->magicCall && $reflClass->hasMethod('__call') && $reflClass->getMethod('__call')->isPublic()) {
        //     // we call the getter and hope the __call do the job
        //     $object->$setter($value);
        }
    }

    /**
     * Searches add and remove methods
     *
     * Simplified version of {@link Symfony\Component\PropertyAccess\PropertyAccessor}.
     */
    private function findAdderAndRemover()
    {
        $reflClass = new \ReflectionClass($this->class);
        $singulars = (array) StringUtil::singularify($this->camelize($this->name));

        foreach ($singulars as $singular) {
            $addMethod = 'add'.$singular;
            $removeMethod = 'remove'.$singular;

            $addMethodFound = $this->isAccessible($reflClass, $addMethod, 1);
            $removeMethodFound = $this->isAccessible($reflClass, $removeMethod, 1);

            if ($addMethodFound && $removeMethodFound) {
                $this->adderRemover = $singular;

                return;
            }
        }
    }

    /**
     * Returns whether a method is public and has a specific number of required parameters.
     *
     * Copied from {@link Symfony\Component\PropertyAccess\PropertyAccessor}.
     *
     * @param  \ReflectionClass $class      The class of the method
     * @param  string           $methodName The method name
     * @param  integer          $parameters The number of parameters
     *
     * @return Boolean Whether the method is public and has $parameters
     *                                      required parameters
     */
    private function isAccessible(\ReflectionClass $class, $methodName, $parameters)
    {
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $parameters) {
                return true;
            }
        }

        return false;
    }
}
