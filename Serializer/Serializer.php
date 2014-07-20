<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Serializer;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use ML\JsonLD\JsonLD;
use Doctrine\Common\Util\ClassUtils;
use ML\HydraBundle\DocumentationGenerator;
use ML\HydraBundle\HydraApi;
use ML\HydraBundle\Mapping\OperationDefinition;


/**
 * JSON-LD serializer
 *
 * Serializes annotated objects to a JSON-LD representation.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class Serializer implements SerializerInterface
{
    /**
     * @var HydraApi The Hydra documentation.
     */
    private $hydraApi;

    /**
     * @var RouterInterface The router.
     */
    private $router;

    /**
     * Constructor
     *
     * @param HydraApi        $hydraApi The Hydra documentation.
     * @param RouterInterface $router   The router.
     */
    public function __construct(HydraApi $hydraApi, RouterInterface $router)
    {
        $this->hydraApi = $hydraApi;
        $this->router = $router;
    }

    /**
     * Serializes data in the appropriate format
     *
     * @param mixed  $data    any data
     * @param string $format  format name
     * @param array  $context options normalizers/encoders have access to
     *
     * @return string
     */
    public function serialize($data, $format, array $context = array())
    {
        if ('jsonld' !== $format) {
            throw new UnexpectedValueException('Serialization for the format ' . $format . ' is not supported');
        }

        if (false === is_object($data)) {
            throw new \Exception('Only objects can be serialized');
        }

        return JsonLD::toString($this->doSerialize($data, true), true);
    }

    /**
     * Serializes data
     *
     * @param mixed    $data    The data to serialize.
     * @param boolealn $include Include (embed) the complete data instead of
     *                          of just adding a reference?
     *
     * @return mixed The serialized data.
     */
    private function doSerialize($data, $include = false)
    {
        // TODO Handle cycles and allow subtrees to be embedded instead of just being referenced
        $metadata = $this->hydraApi->getMetadataFor(get_class($data));

        if (null === $metadata) {
            // TODO Improve this error message
            throw new \Exception(sprintf('"%s" cannot be serialized as it is not documented.', get_class($data)));
        }

        $result = array();
        if ($include) {
            $result['@context'] = $this->router->generate('hydra_context', array('type' => $metadata->getExposeAs()));
        }

        if (null !== ($route = $metadata->getRoute())) {
            if (null !== ($url = $this->generateUrl($route, $data))) {
                $result['@id'] = $url;
            }
        }

        if ($include) {
            $result['@type'] = $metadata->getExposeAs();
        } else {
            $result['@type'] = ($metadata->isExternalReference())
                ? $metadata->getIri()
                : 'vocab:' . $metadata->getIri();
        }

        if (false === $include) {
            return $result;
        }


        foreach ($metadata->getProperties() as $property) {
            if ($property->isWriteOnly()) {
                continue;
            }

            $value = $property->getValue($data);

            if (null !== ($route = $property->getRoute())) {
                if (false === $value) {
                    continue;
                }

                $url = $this->generateUrl($route, $data, $value);

                if (null !== $url) {
                    $result[$property->getExposeAs()] = $url;
                }

                continue;
            }

            // TODO Recurse
            if (is_object($value) && $this->hydraApi->hasNormalizer(get_class($value))) {
                $normalizer = $this->hydraApi->getNormalizer(get_class($value));
                $result[$property->getExposeAs()] = $normalizer->normalize($value);
            } elseif (is_array($value) || ($value instanceof \ArrayAccess) || ($value instanceof \Travesable)) {
                $result[$property->getExposeAs()] = array();
                foreach ($value as $val) {
                    $result[$property->getExposeAs()][] = $this->doSerialize($val);
                }
            } else {
                $result[$property->getExposeAs()] = $value;
            }
        }

        return $result;
    }

    /**
     * Generate a URL for the specified operation using the passed entity/data
     *
     * @param OperationDefinition $operation    The operation specifying the route
     * @param mixed               $entity       The entity to be used to fill URL variables
     * @param mixed               $value        A value to be used to fill URL variables
     *
     * @return string|null The URL or null if the URL shouldn't be exposed.
     */
    private function generateUrl(OperationDefinition $operation, $entity, $value = null)
    {
        $route = $operation->getRoute();
        $routeVariables = $route->compile()->getVariables();
        $variableValues = $route->getDefaults();
        unset($variableValues['_controller']);

        // TODO: Allow custom route variable mappings
        if (is_array($value)) {
            $variableValues += $value;  // FIXXME: Check if this is really what we want in all cases
        } elseif (1 === count($routeVariables)) {
            if (is_scalar($value)) {
                $variableValues[reset($routeVariables)] = $value;
            } elseif (is_object($value) && is_callable(array($value, 'getId'))) {
                // TODO Make the is_callable check more robust
                $variableValues[reset($routeVariables)] = $value->getId();
            } elseif (is_object($entity) && is_callable(array($entity, 'getId'))) {
                // TODO Check if this is want in all cases
                $variableValues[reset($routeVariables)] = $entity->getId();
            } elseif (null === $value) {
                return null;
            }
        } else {
            $accessor = PropertyAccess::createPropertyAccessor();

            foreach ($routeVariables as $variable) {
                try {
                    $variableValues[$variable] = $accessor->getValue($value, $variable);
                } catch (\Exception $e) {
                    // do nothing, no such property exists
                }
            }

        }

        return $this->router->generate($operation->getName(), $variableValues);
    }

    /**
     * Deserializes data into the given type.
     *
     * @param mixed  $data
     * @param string $type
     * @param string $format
     * @param array  $context
     *
     * @return object
     */
    public function deserialize($data, $type, $format, array $context = array())
    {
        if ('jsonld' !== $format) {
            throw new UnexpectedValueException('Deserialization for the format ' . $format . ' is not supported');
        }

        $reflectionClass = new \ReflectionClass($type);

        if (null !== ($constructor = $reflectionClass->getConstructor())) {
            if (0 !== $constructor->getNumberOfRequiredParameters()) {
                throw new RuntimeException(
                    'Cannot create an instance of '. $type .
                    ' from serialized data because its constructor has required parameters.'
                );
            }
        }

        return $this->doDeserialize($data, new $type);
    }

    /**
     * Deserializes data into an existing entity
     *
     * @param mixed  $data   The data to deserialize.
     * @param object $entity The entity into which the data should be
     *                       deserialized.
     *
     * @return object The entity.
     */
    public function deserializeIntoEntity($data, $entity)
    {
        return $this->doDeserialize($data, $entity);
    }

    /**
     * Deserializes JSON-LD data
     *
     * @param mixed  $data   The data to deserialize.
     * @param object $entity The entity into which the data should be
     *                       deserialized.
     *
     * @return object The entity.
     */
    private function doDeserialize($data, $entity)
    {
        $metadata = $this->hydraApi->getMetadataFor(get_class($entity));

        if (null === $metadata) {
            // TODO Improve this error message
            throw new \Exception(sprintf('"%s" cannot be serialized as it is not documented.', get_class($data)));
        }

        $vocabPrefix = $this->router->generate('hydra_vocab', array(), true) . '#';
        $typeIri = ($metadata->isExternalReference())
            ? $metadata->getIri()
            : $vocabPrefix . $metadata->getIri();

        $graph = JsonLD::getDocument($data)->getGraph();
        $node = $graph->getNodesByType($typeIri);

        if (1 !== count($node)) {
            throw new RuntimeException(
                'The passed data contains '. count($node) . ' nodes of the type ' .
                $typeIri . '; expected 1.'
            );
        }

        $node = reset($node);

        foreach ($metadata->getProperties() as $property) {
            if ($property->isReadOnly()) {
                continue;
            }

            // TODO Parse route!
            if (null !== ($route = $property->getRoute())) {
                continue;   // FIXXE Handle properties whose value are URLs
            }

            // TODO Recurse!?
            $propertyIri = ($property->isExternalReference())
                ? $property->getIri()
                : $vocabPrefix . $property->getIri();

            $value = $node->getProperty($propertyIri);
            if ($value instanceof \ML\JsonLD\Value) {
                $value = $value->getValue();
            }

            if ($this->hydraApi->hasNormalizer($property->getType())) {
                $normalizer = $this->hydraApi->getNormalizer($property->getType());
                $value = $normalizer->denormalize($value, $property->getType());
            }

            $property->setValue($entity, $value);  // TODO Fix IRI construction

            // if (is_array($value) || ($value instanceof \ArrayAccess) || ($value instanceof \Travesable)) {
            //     $result[$property] = array();
            //     foreach ($value as $val) {
            //         $result[$property][] = $this->doSerialize($val);
            //     }
            // } else {
            //     $result[$property] = $value;
            // }
        }

        return $entity;
    }
}
