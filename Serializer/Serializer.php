<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Serializer;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Routing\RouterInterface;
use ML\JsonLD\JsonLD;
use Doctrine\Common\Util\ClassUtils;
use ML\HydraBundle\DocumentationGenerator;


/**
 * JSON-LD serializer
 *
 * Serializes annotated objects to a JSON-LD representation.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class Serializer implements SerializerInterface
{
    protected $docu;
    protected $types;
    protected $routes;
    protected $router;

    public function __construct(DocumentationGenerator $documentationGenerator, RouterInterface $router)
    {
        $this->docu = $documentationGenerator->getDocumentation();
        $this->types = $this->docu['types'];  // TODO Remove this!?
        $this->routes = $this->docu['routes'];  // TODO Remove this!?
        $this->router = $router;
    }

    /**
     * Serializes data in the appropriate format
     *
     * @param mixed  $data   any data
     * @param string $format format name
     * @return string
     */
    public function serialize($data, $format)
    {
        // TODO Allow scalars to be serialized directly?
        if (false === is_object($data)) {
            throw new \Exception('Only objects can be serialized');
        }

        // TODO Fix this to support Doctrine collections
        $type = get_class($data);

        return JsonLD::toString($this->doSerializeNew($data, true), true);
    }

    /**
     * Deserializes data into the given type.
     *
     * @param mixed  $data
     * @param string $type
     * @param string $format
     * @return object
     */
    public function deserialize($data, $type, $format)
    {

    }

    /**
     * Serializes data
     *
     * @param mixed  $data        The data to serialize.
     * @param string $type        The datatype to use.
     * @param bool   $asReference Serialize as reference (instead of embed).
     * @param array  $include     Which subtrees should be included?
     *
     * @return mixed The serialized data.
     */
    private function doSerialize($data, $type, $asReference = false, array $include = array())
    {
        $type = $this->docu['class2type'][$type];

        if (false === isset($this->types[$type])) {
            throw new \Exception($type . ' is not documented.');
        }

        $result = new \stdClass();

        if ($asReference && array_key_exists('@id', $this->types[$type]['properties'])) {
            $element = $this->types[$type]['properties']['@id']['element'];
            $id = new PropertyPath($element);
            $result->{'@id'} = $this->router->generate(
                $this->types[$type]['properties']['@id']['route'],
                array($element => $id->getValue($data)),
                true
            );

            return $result;
        }

        $result->{'@context'} = $type;

        foreach ($this->types[$type]['properties'] as $key => $definition) {
            $subtreeAsReference = !in_array($key, $include);
            $subtreeIncludes = array();
            if ($subtreeAsReference && array_key_exists($definition['element'], $include)) {
                $subtreeAsReference = false;
                $subtreeIncludes = $include[$definition['element']];
            }

            $propertyPath = $definition['element'];

            // TODO Look for a way to avoid this hack!
            if ('get' === substr($propertyPath, 0, 3)) {
                $propertyPath = substr($propertyPath, 3);
            }
            $propertyPath = new PropertyPath($propertyPath);

            $value = $propertyPath->getValue($data);

            if (is_array($value) || ($value instanceof \Travesable) || ($value instanceof \IteratorAggregate)) {
                if (isset($definition['route'])) {
                    // the value is an array of IRI template variables
                    $result->$key = new \stdClass();
                    $result->$key->{'@id'} = $this->router->generate(
                        $definition['route'],
                        $value,
                        true
                    );
                } else {
                    $serializedItems = array();
                    foreach ($value as $item) {

                        $serializedItems[] = $this->doSerialize(
                            $value,
                            isset($definition['array_type']) ? $definition['array_type'] : null,
                            $subtreeAsReference,
                            $subtreeIncludes
                        );
                    }
                    $result->$key = $serializedItems;
                }
            } elseif (is_object($value)) {
                $result->$key = $this->doSerialize($value, $definition['type'], $subtreeAsReference, $subtreeIncludes);
            } else {
                if (isset($definition['route'])) {
                    $result->$key = $this->router->generate(
                        $definition['route'],
                        array($definition['element'] => $value),
                        true
                    );
                } else {
                    $result->$key = $value;
                }
            }
        }

        return $result;
    }


    /**
     * Serializes data NEW
     *
     * @param mixed  $data        The data to serialize.
     * @param bool   $asReference Serialize as reference (instead of embed).
     * @param array  $include     Which subtrees should be included?
     *
     * @return mixed The serialized data.
     */
    private function doSerializeNew($data, $include = false)
    {
        // TODO Need to handle cycles!

        if (is_array($data)) {
            die ('HydraCollection');
        } elseif (is_object($data)) {
            $className = class_exists('Doctrine\Common\Util\ClassUtils')
                ? ClassUtils::getClass($data)
                : get_class($data);

            $type = $this->docu['class2type'][$className];

            if (false === $include) {
                $result = array();

                if (isset($this->docu['types'][$type]['properties']['@id'])) {
                    $result['@id'] = $this->router->generate($this->docu['types'][$type]['properties']['@id']['route'], array('id' => $data->getId()));
                    $result['@type'] = 'vocab:' . $type;
                }

                return $result;
            }

            // TODO Throw exception if type is not documented ==> not exposed
            $result = array('@context' => $this->router->generate('hydra_context', array('type' => $type)));

            foreach ($this->docu['types'][$type]['properties'] as $property => $definition) {
                if ($definition['writeonly']) {
                    continue;
                }

                if (isset($definition['route'])) {
                    $reqVariables = $this->docu['routes'][$definition['route']]['variables'];
                    $parameters = $this->docu['routes'][$definition['route']]['defaults'];

                    if (isset($definition['route_variables'])) {
                        foreach ($definition['route_variables'] as $var => $def) {
                            if ($def[1]) { // is method?
                                $parameters[$var] = $data->{$def[0]}();
                            } else {
                                $parameters[$var] = $data->{$def[0]};
                            }
                        }
                    } else {
                        $value = $this->getValue($data, $definition);
                        if (is_array($value)) {
                            $parameters += $value;
                        } elseif (is_scalar($value) && (1 === count($reqVariables))) {
                            $parameters[$reqVariables[0]] = $value;
                        }
                    }

                    // TODO Remove this hack
                    if (in_array('id', $reqVariables) && !isset($parameters['id']) && is_callable(array($data, 'getId'))) {
                        $parameters['id'] = $data->getId();
                    }

                    $route =  $this->router->generate($definition['route'], $parameters);

                    if ('HydraCollection' === $definition['type']) {
                        $result[$property] = array(
                            '@id' => $route,
                            '@type' => 'hydra:Collection'
                        );
                    } else {
                        $result[$property] = $route;
                    }

                    // Add @type after @id
                    if ('@id' === $property) {
                        $result['@type'] = $type;
                    }

                    continue;
                }

                // TODO Recurse

                $value = $this->getValue($data, $definition);

                if (is_array($value) || ($value instanceof \ArrayAccess) || ($value instanceof \Travesable)) {
                    $result[$property] = array();
                    foreach ($value as $val) {
                        $result[$property][] = $this->doSerializeNew($val);
                    }
                } else {
                    $result[$property] = $value;
                }
            }

            // if ($this->docu['types'][$type]['operations']) {
            //     $result['hydra:operations'] = array();
            //     foreach ($this->docu['types'][$type]['operations'] as $route) {
            //         $def = $this->docu['routes'][$route];
            //         $statusCodes = array();

            //         if ($def['status_codes']) {
            //             foreach ($def['status_codes'] as $code => $desc) {
            //                 $statusCodes[] = array(
            //                     'hydra:statusCode'  => $code,
            //                     'hydra:description' => $desc,
            //                 );
            //             }
            //         }

            //         $expects = $this->docu['class2type'][$def['expect']];
            //         $returns = $def['return']['type'];
            //         if ('HydraCollection' === $returns) {
            //             $returns = 'hydra:Collection';
            //         } elseif ('array' === $returns) {
            //             $returns = $this->docu['class2type'][$def['return']['type']['array_type']];
            //         } else {
            //             $returns = $this->docu['class2type'][$returns];
            //         }

            //         $result['hydra:operations'][] = array(
            //             'hydra:method'      => $def['method'],
            //             'hydra:title'       => $def['title'],
            //             'hydra:description' => $def['description'],
            //             // TODO Transform types to vocab references
            //             'hydra:expects'    => $expects,
            //             'hydra:returns'    => $returns,
            //             'hydra:statusCodes' => $statusCodes
            //         );
            //     }
            // }

            return $result;
        }
    }

    private function getValue($object, $definition)
    {
        if (isset($definition['getter'])) {
            if ($definition['getter_is_method']) {
                return $object->{$definition['getter']}();
            } else {
                return $object->{$definition['getter']};
            }
        }

        return null;
    }
}
