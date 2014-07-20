<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;

/**
 * A Hydra Operation
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class OperationDefinition
{
    /**
     * @var string The name of the operation
     */
    private $name;

    /**
     * @var string The IRI used to identify this operation
     */
    private $iri;

    /**
     * @var string The IRI identifying the type of this operation
     */
    private $type;

    /**
     * @var string The title of the description of this operation
     */
    private $title;

    /**
     * @var string A description of this operation
     */
    private $description;

    /**
     * @var string The HTTP method of this operation
     */
    private $method;

    /**
     * @var string The data expected by this operation
     */
    private $expects;

    /**
     * @var string The data returned by this operation
     */
    private $returns;

    /**
     * @var array Additional information about status codes returned by this
     *            operation
     */
    private $statusCodes;

    /**
     * Constructor
     *
     * @param string $name The name of the operation being documented.
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Gets the name of this operation
     *
     * @return string The the name of this operation.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the IRI identifying this operation
     *
     * @return string The IRI identifying this operation.
     *
     * @return OperationDefinition $this
     */
    public function setIri($iri)
    {
        $this->iri = $iri;

        return $this;
    }

    /**
     * Gets the IRI identifying this operation
     *
     * @return string The IRI identifying this operation.
     */
    public function getIri()
    {
        return $this->iri;
    }

    /**
     * Does this operation definition represent a reference to an external
     * definition?
     *
     * An external definition can be used but not modified or further
     * annotated as it is not under the control of this system.
     *
     * @return boolean True if this operation definition represents an
     *                 external reference, false otherwise.
     */
    public function isExternalReference()
    {
        return strpos($this->iri, ':') !== false;
    }

    /**
     * Sets the IRI identifying the type of this operation
     *
     * @return string The IRI identifying the type of this operation.
     *
     * @return OperationDefinition $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the IRI identifying the type of this operation
     *
     * @return string The IRI identifying the type of this operation.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the title of the description of this operation
     *
     * @param string $title The title of the description of this operation.
     *
     * @return OperationDefinition $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gets the title of the description of this operation
     *
     * @return string The title of the description of this operation.
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Sets the description of this operation
     *
     * @param string $description The description of this operation.
     *
     * @return OperationDefinition $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Gets the description of this operation
     *
     * @return string The description of this operation.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Sets the HTTP method of this operation
     *
     * @param string $method The method of this operation.
     *
     * @return OperationDefinition $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Gets the HTTP method of this operation
     *
     * @return string The method of this operation.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the data expected by this operation
     *
     * This is either a fully-qualified class name or an absolute IRI.
     * Absolute IRIs can be detected by looking for a colon.
     *
     * @param string $expects The data expected by this operation.
     *
     * @return OperationDefinition $this
     */
    public function setExpects($expects)
    {
        $this->expects = $expects;

        return $this;
    }

    /**
     * Gets the data expected by this operation
     *
     * This is either a fully-qualified class name or an absolute IRI.
     * Absolute IRIs can be detected by looking for a colon.
     *
     * @return string The data expected by this operation.
     */
    public function getExpects()
    {
        return $this->expects;
    }

    /**
     * Sets the data returned by this operation
     *
     * This is either a fully-qualified class name or an absolute IRI.
     * Absolute IRIs can be detected by looking for a colon.
     *
     * @param string $expects The data returned by this operation.
     *
     * @return OperationDefinition $this
     */
    public function setReturns($returns)
    {
        $this->returns = $returns;

        return $this;
    }

    /**
     * Gets the data returned by this operation
     *
     * This is either a fully-qualified class name or an absolute IRI.
     * Absolute IRIs can be detected by looking for a colon.
     *
     * @return string The data returned by this operation.
     */
    public function getReturns()
    {
        return $this->returns;
    }

    /**
     * Sets additional information about the status codes this operation
     * might return
     *
     * @param array $statusCodes Additional information about status codes
     *                           returned by this operation
     *
     * @return OperationDefinition $this
     */
    public function setStatusCodes($statusCodes)
    {
        $this->statusCodes = $statusCodes;

        return $this;
    }

    /**
     * Gets additional information about the status codes this operation
     * might return
     *
     * @return array Additional information about status codes returned by
     *               this operation
     */
    public function getStatusCodes()
    {
        return $this->statusCodes;
    }

    /**
     * Sets the route to create IRIs identifying instances of this operation
     *
     * @param string $route The route to create IRIs identifying instances
     *                      of this operation.
     *
     * @return OperationDefinition $this
     */
    public function setRoute($route)
    {
        $this->route = $route;

        return $this;
    }

    /**
     * Gets the route to create IRIs identifying instances of this operation
     *
     * @return string The route to create IRIs identifying instances of this
     *                operation.
     */
    public function getRoute()
    {
        return $this->route;
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
}
