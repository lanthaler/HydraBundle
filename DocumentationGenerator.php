<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle;

use Doctrine\Common\Annotations\Reader;
use ML\HydraBundle\Mapping\Operation;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The DocumentationGenerator
 */
class DocumentationGenerator
{
    const OPERATION_ANNOTATION = 'ML\\HydraBundle\\Mapping\\Operation';

    const COLLECTION_ANNOTATION = 'ML\\HydraBundle\\Mapping\\Collection';

    const HYDRA_COLLECTION = 'ML\\HydraBundle\\Collection\\Collection';

    private static $typeMap = array(
        'string' => 'http://www.w3.org/2001/XMLSchema#string',
        'integer' => 'http://www.w3.org/2001/XMLSchema#integer',
        'float' => 'http://www.w3.org/2001/XMLSchema#double',
        'double' => 'http://www.w3.org/2001/XMLSchema#double',
        'boolean' => 'http://www.w3.org/2001/XMLSchema#boolean',
        'bool' => 'http://www.w3.org/2001/XMLSchema#boolean',
        '@id' => 'http://www.w3.org/2001/XMLSchema#anyURI',
        'void' => 'http://www.w3.org/2002/07/owl#Nothing'
    );

    /**
     * @var \Symfony\Component\DependencyInjection\ContainerInterface
     */
    private $container;

    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    private $router;

    /**
     * @var \Doctrine\Common\Annotations\Reader
     */
    private $reader;

    /**
     * Constructor
     *
     * @param ContainerInterface $container The service container.
     * @param RouterInterface    $router    The router.
     * @param Reader             $reader    The annotation reader.
     */
    public function __construct(ContainerInterface $container, RouterInterface $router, Reader $reader)
    {
        $this->container = $container;
        $this->router    = $router;
        $this->reader    = $reader;
    }

    public function isOperation(\ReflectionMethod $method)
    {
        if ($this->getAnnotation($method, self::OPERATION_ANNOTATION)) {
            return true;
        }

        return false;
    }

    /**
     * Gets the documentation
     *
     * @return array
     */
    public function getDocumentation()
    {
        $array = array();
        $resources = array();
        $documentation = array();
        $documentation['rels'] = array();
        $documentation['types2document'] = array();
        $documentation['vocab_base'] = $this->router->generate('hydra_vocab');
        $documentation['vocab_base_abs'] = $this->router->generate('hydra_vocab', array(), true);

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            if ($method = $this->getReflectionMethod($route->getDefault('_controller'))) {
                if ($annotation = $this->getAnnotation($method, self::OPERATION_ANNOTATION)) {
                    $this->documentRoute($documentation, $name, $route, $annotation, $method);
                }
            }
        }
        while (count($documentation['types2document']) > 0)
        {
            $type = array_pop($documentation['types2document']);
            $this->documentType($documentation, $type);
        }

        unset($documentation['types2document']);

        return $documentation;
    }

    public function getVocabulary()
    {
        $documentation = $this->getDocumentation();

        $vocab = array();
        $vocabPrefix = $this->router->generate('hydra_vocab', array(), true) . '#';

        foreach ($documentation['types'] as $type => $definition) {
            $description = $definition['title'];
            if ($description && $definition['description']) {
                $description .= "\n\n";
            }
            $description .= $definition['description'];

            $vocab[] = array(
                '@id' => $this->getElementIri($vocabPrefix, $definition['iri']),
                '@type' => 'rdfs:Class',
                'label' => $type,
                'description' => $description,
                'operations' => $this->getOperations4Vocab($documentation, $definition['operations'], $vocabPrefix),
            );

            foreach ($definition['properties'] as $name => $property) {
                if ('@id' === $name) {
                    continue;
                }

                $description = $property['title'];
                if ($description && $property['description']) {
                    $description .= "\n\n";
                }
                $description .= $property['description'];

                $vocab[] = array(
                    '@id' => $this->getElementIri($vocabPrefix, $property['iri']),   // TODO Check this
                    '@type' => 'rdfs:Property',
                    'label' => $name,
                    'description' => $description,
                    'domain' => $this->getElementIri($vocabPrefix, $definition['iri']),
                    'range' => $this->getRangeIri($vocabPrefix, $property['type'], $property['array_type']),
                    'readonly' => $property['readonly'],
                    'writeonly' => $property['writeonly'],
                    'operations' => $this->getOperations4Vocab($documentation, $property['operations'], $vocabPrefix)
                );
            }
        }

        // TODO Remove this??
        // Add XSD types to vocab so that they don't have to be retrieved remotely
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#string',
            '@type' => 'rdfs:Datatype',
            'label' => 'string',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#integer',
            '@type' => 'rdfs:Datatype',
            'label' => 'integer',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#float',
            '@type' => 'rdfs:Datatype',
            'label' => 'float',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#double',
            '@type' => 'rdfs:Datatype',
            'label' => 'double',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#boolean',
            '@type' => 'rdfs:Datatype',
            'label' => 'boolean',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2001/XMLSchema#anyURI',
            '@type' => 'rdfs:Datatype',
            'label' => 'IRI',
            'description' => ''
        );
        $vocab[] = array(
            '@id' => 'http://www.w3.org/2002/07/owl#Nothing',
            '@type' => 'http://www.w3.org/2002/07/owl#Class',
            'label' => 'void',
            'description' => ''
        );


        $vocab = array(
            '@context' => array(
                'vocab' => $vocabPrefix,
                'hydra' => 'http://purl.org/hydra/core#',
                'readonly' => 'hydra:readonly',
                'writeonly' => 'hydra:writeonly',
                'operations' => 'hydra:operations',
                'expects' =>  array('@id' => 'hydra:expects', '@type' => '@id'),
                'returns' =>  array('@id' => 'hydra:returns', '@type' => '@id'),
                'status_codes' => 'hydra:statusCodes',
                'code' => 'hydra:statusCode',
                'rdfs' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'label' => 'rdfs:label',
                'description' => 'rdfs:comment',
                'domain' => array('@id' => 'rdfs:domain', '@type' => '@id'),
                'range' =>  array('@id' => 'rdfs:range', '@type' => '@id'),
            ),
            '@graph' => $vocab
        );

        return $vocab;
    }

    private function getOperations4Vocab($documentation, $operations, $vocabPrefix)
    {
        $result = array();
        foreach ($operations as $operation) {
            $statusCodes = array();
            foreach ($documentation['routes'][$operation]['status_codes'] as $code => $description) {
                $statusCodes[] = array(
                    'code' => $code,
                    'description' => $description
                );
            }

            $expects = $documentation['routes'][$operation]['expect'];
            if ($expects && isset($documentation['class2type'][$expects])) {
                  // TODO Handle relative IRIs!?
                $expects = $this->getElementIri($vocabPrefix, $documentation['types'][$documentation['class2type'][$expects]]['iri']);
            }

            $returns = $documentation['routes'][$operation]['return']['type'];
            if ($returns && isset($documentation['class2type'][$returns])) {
                $returns = $this->getElementIri($vocabPrefix, $documentation['types'][$documentation['class2type'][$returns]]['iri']);
            }

            $result[] = array(
                '@id' => '_:' . $operation,
                'method' => $documentation['routes'][$operation]['method'],
                'label' => $documentation['routes'][$operation]['title'],
                'description' => $documentation['routes'][$operation]['description'],
                'expects' => $expects,
                'returns' => $returns,
                'status_codes' => $statusCodes
            );
        }

        return $result;
    }

    public function getContext($type)
    {
        $documentation = $this->getDocumentation();

        if (!isset($documentation['types'][$type])) {
            return null;
        }

        $properties = $documentation['types'][$type]['properties'];
        unset($properties['@id']);

        $context = array();
        $context['vocab'] = $this->router->generate('hydra_vocab', array(), true) . '#';
        $context['hydra'] = 'http://purl.org/hydra/core#';

        $context[$type] = $this->getElementCompactIri(
            $context['vocab'],
             'vocab',
             $documentation['types'][$type]['iri']
        );

        $ranges = array();

        foreach ($properties as $property => $def) {
            $context[$property] = $this->getElementCompactIri($context['vocab'], 'vocab', $def['iri']);

            // TODO Make this check more robust
            if (('@id' === $def['type']) || (self::HYDRA_COLLECTION === $def['type'])) {
                $context[$property] = array('@id' => $context[$property], '@type' => '@id');
            }
        }

        return array('@context' => $context);
    }

    private function getElementIri($vocabPrefix, $iri)
    {
        return (false !== strpos($iri, ':')) ? $iri : $vocabPrefix . $iri;  // TODO Handle relative IRIs!?
    }

    private function getElementCompactIri($vocabIri, $vocabPrefix, $iri)
    {
        if (false !== strpos($iri, ':')) {
            if ($vocabIri === substr($iri, 0, strlen($vocabIri))) {
                return $vocabPrefix . ':' . substr($iri, strlen($vocabIri));
            }

            return $iri;
        } else {
            // it's just a fragment
            return $vocabPrefix . ':' . $iri;
        }
    }

    private function getRangeIri($vocabPrefix, $type, $arrayType)
    {
        if ('array' === $type) {
            if (null === $arrayType) {
                return null;
            }

            $type = $arrayType;
        }

        if (isset(self::$typeMap[$type])) {
            return self::$typeMap[$type];
        }

        $documentation = $this->getDocumentation();
        if (!isset($documentation['class2type'][$type])) {
            throw new \Exception('Found invalid type: ' . $type);
        }

        return $this->getElementIri($vocabPrefix, $documentation['types'][$documentation['class2type'][$type]]['iri']);
    }

    /**
     * Returns the ReflectionMethod for the given controller string.
     *
     * @param string $controller
     * @return \ReflectionMethod|null
     */
    public function getReflectionMethod($controller)
    {
        if (preg_match('#(.+)::([\w]+)#', $controller, $matches)) {
            $class = $matches[1];
            $method = $matches[2];
        } elseif (preg_match('#(.+):([\w]+)#', $controller, $matches)) {
            $controller = $matches[1];
            $method = $matches[2];
            if ($this->container->has($controller)) {
                $this->container->enterScope('request');
                $this->container->set('request', new Request());
                $class = get_class($this->container->get($controller));
                $this->container->leaveScope('request');
            }
        }

        if (isset($class) && isset($method)) {
            try {
                return new \ReflectionMethod($class, $method);
            } catch (\ReflectionException $e) {
            }
        }

        return null;
    }

    /**
     * Returns a new Documentation instance with more data.
     *
     * @param array             $documentation
     * @param string            $routeName
     * @param Route             $route
     * @param Operation         $annotation
     * @param \ReflectionMethod $method
     */
    protected function documentRoute(&$documentation, $routeName, Route $route, Operation $annotation, \ReflectionMethod $method)
    {
        $doc = $this->getDocBlockText($method);

        if (null !== $annotation->expect) {
             $documentation['types2document'][] = $annotation->expect;
        }
        $doc['expect'] = $annotation->expect;
        $doc['status_codes'] = $annotation->status_codes;

        $doc['return'] = $this->getType($method);


        if (('array' === $doc['return']['type']) || (self::HYDRA_COLLECTION === $doc['return']['type'])) {
            $doc['return']['type'] = self::HYDRA_COLLECTION;
        }

        if ($doc['return']['type'] && !static::isPrimitiveType($doc['return']['type'])) {
                 $documentation['types2document'][] = $doc['return']['type'];
        }
        if ($doc['return']['array_type'] && !static::isPrimitiveType($doc['return']['array_type'])) {
             $documentation['types2document'][] = $doc['return']['array_type'];
        }

        // method/IRI
        $doc['method'] = $route->getRequirement('_method') ?: 'ANY';
        $doc['iri'] = $route->getPattern();
        $doc['variables'] = $route->compile()->getVariables();
        $doc['defaults'] = $route->getDefaults();
        unset($doc['defaults']['_controller']);

        $documentation['routes'][$routeName] = $doc;
    }

    protected function getDocBlockText(\Reflector $element)
    {
        $text = $element->getDocComment();

        // store @var annotations if available (just one line supported)
        preg_match('/@var\s+([^\s]+)((?:[ ]+)[^\r\n]*)?/', $text, $var);

        // then remove annotations
        $text = preg_replace('/^\s+\* @[\w0-9]+.*/msi', '', $text);

        // let's clean the doc block
        $text = str_replace('/**', '', $text);
        $text = str_replace('*/', '', $text);
        $text = str_replace('*', '', $text);
        $text = str_replace("\r", '', trim($text));
        $text = preg_replace("#^\n[ \t]+[*]?#i", "\n", trim($text));
        $text = preg_replace("#[\t ]+#i", ' ', trim($text));
        $text = str_replace("\"", "\\\"", $text);

        // let's clean the doc block
        // $text = str_replace('/**', '', $text);
        // $text = str_replace('*/', '', $text);
        // $text = preg_replace('/^\s*\* ?/m', '', $text);

        $parts = explode("\n", $text, 2);
        $parts[0] = trim($parts[0]);
        $parts[1] = isset($parts[1]) ? trim($parts[1]) : '';

        if (('' === $parts[0]) && isset($var[2])) {
            $parts[0] = trim($var[2]);
        }

        if ('' !== $parts[1]) {
            $parts[1] = preg_replace("#\s*\n\s*\n\s*#", '@@KEEPPARAGRAPHS@@', $parts[1]);
            $parts[1] = preg_replace("#\s*\n\s*#", ' ', $parts[1]);
            $parts[1] = str_replace('@@KEEPPARAGRAPHS@@', "\n", $parts[1]);
        }

        return array('title' => $parts[0], 'description' => $parts[1]);
    }

    /**
     * Documents a type
     *
     * @param  array  $documentation The documentation.
     * @param  string $class         The type to document.
     * @param  string $group         The serialization group to use.
     */
    protected function documentType(&$documentation, $class, $group = null)
    {
        if (self::isPrimitiveType($class)) {
            return;
        }

        $linkRelationAnnot = 'ML\\HydraBundle\\Mapping\\LinkRelation';
        $idAnnot = 'ML\\HydraBundle\\Mapping\\Id';
        $exposeAnnot = 'ML\\HydraBundle\\Mapping\\Expose';

        $class = new \ReflectionClass($class);
        $exposeClassAs = $class->getShortName();

        if (null === ($annotation = $this->getAnnotation($class, $exposeAnnot))) {
            // TODO Improve this
            throw new \Exception($class->name . ' is directly or indirectly exposed but not annotated accordingly.');
        } else {
            if ($annotation->as) {
                $exposeClassAs = $annotation->as;
            }
        }

        if (isset($documentation['types'][$exposeClassAs])) {
            if ($class->name !== $documentation['types'][$exposeClassAs]['class']) {
                throw new \Exception(sprintf('The classes "%s" and "%s" have the same name (the namespace is ignored).',
                    $class->name,
                    $documentation['types'][$exposeClassAs]['class'])
                );
            }

            return;
        }

        $result = array();
        $result += $this->getDocBlockText($class);
        $result['iri'] = ($annotation->iri) ? $annotation->iri : $exposeClassAs;
        $result['class'] = $class->name;
        $result['properties'] = array();

        if (null !== ($annotation = $this->getAnnotation($class, $idAnnot))) {
            $variables = $annotation->variables;
            $routeVarGetters = array();

            if (0 === count($variables)) {
                if ($class->hasMethod('getId')) {
                    $method = $class->getMethod('getId');
                    if ($method->isPublic() && (0 === $method->getNumberOfRequiredParameters())) {
                        $routeVarGetters['id'] = array('getId', true);
                    }
                }
            }

            // TODO Handle all other possibilities

            $result['properties']['@id'] = array(
                'element' => null,
                'original_type' => null,
                'type' => '@id',
                'title' => 'The entity\'s IRI.',
                'description' => '',
                'route' => $annotation->route,   // TODO Need to check if GET allowed?
                'route_variables' => $routeVarGetters,
                'readonly' => true,
                'writeonly' => false,
                'operations' => array()
            );

            // TODO Check that the IRI template can be filled!?
        }

        $this->documentOperations($class, $result, $documentation);

        $interfaces = $class->getInterfaces();
        $linkRelationMethods = array();
        foreach ($interfaces as $interface) {
            if (null !== $this->getAnnotation($interface, $linkRelationAnnot)) {
                if (false === isset($documentation['rels'][$interface->name])) {
                    $documentation['rels'][$interface->name] = array();
                    foreach ($interface->getMethods() as $method) {
                        if ($method->isPublic()) {
                            $documentation['rels'][$interface->name][$method->name] = $interface->name;
                        }
                    }
                }

                $linkRelationMethods += $documentation['rels'][$interface->name];
            }
        }

        $elements = array_merge($class->getProperties(), $class->getMethods());

        foreach ($elements as $element) {
            if (null === ($annotation = $this->getAnnotation($element, $exposeAnnot))) {
                continue;
            }

            $definition = array();
            $definition['element'] = $element->name;
            $definition += $this->getType($element);
            $definition += $this->getGetterSetter($class, $element);

            $exposeAs = $element->name;
            if ($annotation->as) {
                $exposeAs = $annotation->as;

                if ($annotation->iri) {
                    $definition['iri'] = $annotation->iri;
                } else {
                    $definition['iri'] = $exposeAs;
                }
            } else {
                $exposeAs = $this->propertirize($exposeAs);

                if ($annotation->iri) {
                    $definition['iri'] = $annotation->iri;
                } else {
                    $definition['iri'] = $this->camelize($exposeAs);
                    $definition['iri'][0] = strtolower($definition['iri'][0]);
                }
            }


            $definition['readonly'] = $annotation->readonly;
            $definition['writeonly'] = $annotation->writeonly;
            $definition += $this->getDocBlockText($element);

            if (null !== ($collection = $this->getAnnotation($element, self::COLLECTION_ANNOTATION))) {
                $collection = $collection->route;

                if (!isset($documentation['routes'][$collection]['return']['type'])) {
                    throw new \Exception(sprintf('"%s" in class "%s" is annotated as collection using the route "%s". The route, however, isn\'t annotated.',
                        $element->name, $class->name, $collection));
                }

                if (self::HYDRA_COLLECTION !== $documentation['routes'][$collection]['return']['type']) {
                    // TODO Improve this
                    throw new \Exception(sprintf('"%s" in class "%s" is annotated as collection using the route "%s". The route, however, doesn\'t return a collection',
                        $element->name, $class->name, $collection));
                }

                if ((null !== $definition['type']) && ('array' !== $definition['type'])) {
                    // TODO Improve this
                    throw new \Exception($element->name . ' is being converted to a collection, it\'s return value must therefore be an array');
                }

                $definition['type'] = self::HYDRA_COLLECTION;
                $definition['array_type'] = $documentation['routes'][$collection]['return']['array_type'];
                // TODO Check that the IRI template can be filled!?
                $definition['route'] = $collection;
            }
            $definition['collection'] = $collection;

            $this->documentOperations($element, $definition, $documentation);

            if ($element instanceof \ReflectionMethod) {
                if (array_key_exists($element->name, $linkRelationMethods)) {
                    $definition['original_type'] .= ' --- ' . $linkRelationMethods[$element->name] . '::' . $element->name;
                }
            }

            // TODO Validate definition

            if (isset($result['properties'][$exposeAs])) {
                // TODO Improve this!
                throw new \Exception(sprintf('Both "%s" and "%s" are being exposed as "%s" in class "%s"',
                    $result['properties'][$exposeAs]['element'], $element->name, $exposeAs, $class->name));
            }
            $result['properties'][$exposeAs] = $definition;

            // To avoid deep recursions
            if ($definition['type'] && !static::isPrimitiveType($definition['type'])) {
                 $documentation['types2document'][] = $definition['type'];
            }
            if ($definition['array_type'] && !static::isPrimitiveType($definition['array_type'])) {
                 $documentation['types2document'][] = $definition['array_type'];
            }
        }

        // TODO Do we really need this?
        if (isset($result['properties'])) {
            foreach ($result['properties'] as $property) {
                if (isset($property['operations'])) {
                    foreach ($property['operations'] as $operation) {
                        $documentation['routes'][$operation]['used'] = 'yes';
                    }
                }
            }
        }

        $documentation['class2type'][$class->name] = $exposeClassAs;
        $documentation['types'][$exposeClassAs] = $result;
    }

    /**
     * Document the operations associated to an element
     *
     * @param \Reflector $element  The element being documented.
     * @param array $definition    The element's definition
     * @param array $documentation The so far generated documentation.
     */
    private function documentOperations(\Reflector $element, &$definition, $documentation)
    {
        $operationsAnnot = 'ML\\HydraBundle\\Mapping\\Operations';
        if (null !== ($operations = $this->getAnnotation($element, $operationsAnnot))) {
            $operations = $operations->operations;

            $expectedIriPattern = isset($definition['route'])
                ? $documentation['routes'][$definition['route']]['iri']
                : $documentation['routes'][$operations[0]]['iri'];

            foreach ($operations as $operation) {
                if ($documentation['routes'][$operation]['iri'] !== $expectedIriPattern) {
                    if ($definition['route']) {
                        throw new \Exception(sprintf('The operations (routes: %s) associated with "%s" in "%s" don\'t use the properties IRI pattern "%s".',
                            implode(', ', $operations), $element->name, $class->name, $expectedIriPattern));
                    }
                    throw new \Exception(sprintf('The operations (routes: %s) associated with "%s" in "%s" don\'t use the same IRI pattern.',
                            implode(', ', $operations), $element->name, $class->name));
                }
            }

            if ($element instanceof \ReflectionClass) {
                // TODO Check that the IRI template can be filled!?
                if (false === isset($definition['route'])) {
                    $definition['route'] = $operations[0];
                }
            } elseif (self::HYDRA_COLLECTION !== $definition['type']) {
                // TODO Check this!
                // if ('array' !== $definition['type']) {
                //     throw new \Exception(sprintf('"%s" in "%s" specifies operations but it\'s return type is "%s" instead of array or ' . self::HYDRA_COLLECTION,
                //         $element->name, $element->name, $definition['type']));
                // } else {
                    $definition['type'] = '@id';
                    // TODO Check that the IRI template can be filled!?
                    if (false === isset($definition['route'])) {
                        $definition['route'] = $operations[0];
                    // }
                }
            }
        } else {
            $operations = array();
        }

        $definition['operations'] = $operations;
    }

    /**
     * Get the annotation of an element
     *
     * @param  \Reflector $element    The element whose annotation should be
     *                                retrieved
     * @param  string     $annotation The annotation class to retrieve
     *
     * @return object|null The annotation or null if the annotation doesn't
     *                     exist on that object.
     */
    private function getAnnotation(\Reflector $element, $annotation)
    {
        if ($element instanceof \ReflectionClass) {
            return $this->reader->getClassAnnotation($element, $annotation);
        } elseif ($element instanceof \ReflectionMethod) {
            return $this->reader->getMethodAnnotation($element, $annotation);
        } elseif ($element instanceof \ReflectionProperty) {
            return $this->reader->getPropertyAnnotation($element, $annotation);
        }

        return null;
    }

    public function getType(\Reflector $element)
    {
        $type = ($element instanceof \ReflectionMethod)
            ? $this->getReturnAnnotation($element)
            : $this->getVarAnnotation($element);

        $result = array('type' => $type, 'original_type' => $type, 'array_type' => null);

        if (null === $type) {
            return $result;
        }

        // Checks if the property has @var array<type> or type[] annotation
        if ($type && false !== $pos = strpos($type, '<')) {
            $result['array_type'] = substr($type, $pos + 1, -1);
            $result['type'] = 'array'; //substr($type, 0, $pos);

            // TODO Check this
            // if (isset(self::$typeMap[$arrayType])) {
            //     $arrayType = self::$typeMap[$arrayType];
            // }
        } elseif ('[]' === substr($type, -2)) {
            $result['array_type'] = substr($type, 0, -2);
            $result['type'] = 'array';
        }

        return $result;
    }

    /**
     * Extracts the type of a property using the @var annotation
     *
     * @param  \ReflectionProperty $property The property.
     * @return array The property's type.
     */
    private function getVarAnnotation(\ReflectionProperty $property)
    {
        $result = array();
        // Adapted from Doctrine\Common\Annotations\DocParser::collectAnnotationMetadata()

        // checks if the property has @var annotation
        if ((false !== $propertyComment = $property->getDocComment())
            && false !== strpos($propertyComment, '@var')
            && preg_match('/@var\s+([^\s]+)((?:[ ]+)[^\r\n]*)?/', $propertyComment, $matches)) {   // TODO Fix regex (line end)
            // literal type declaration
            $value = $matches[1];

            return $value;
        }

        return null;
    }

    private function getReturnAnnotation(\ReflectionMethod $method)
    {
        $result = array();
        // Adapted from Doctrine\Common\Annotations\DocParser::collectAnnotationMetadata()

        // checks if the property has @return annotation
        if ((false !== $methodComment = $method->getDocComment())
            && false !== strpos($methodComment, '@return')
            && preg_match('/@return\s+([^\s]+)((?:[ ]+)[^\r\n]*)?/', $methodComment, $matches)) {   // TODO Fix regex (line end)
            // literal type declaration
            $value = $matches[1];

            return $value;
        }

        return null;
    }

    private function getGetterSetter(\ReflectionClass $class, \Reflector $element)
    {
        $definition = array();

        if ($element instanceof \ReflectionProperty) {
            if ($element->isPublic()) {
                $definition['getter'] = $element->name;
                $definition['getter_is_method'] = false;
                $definition['setter'] = $element->name;
                $definition['setter_method'] = false;
            } else {
                $camelProp = $this->camelize($element->name);
                $methods = array(
                    'get' . $camelProp,
                    'is' . $camelProp,
                    'has' . $camelProp
                );

                foreach ($methods as $method) {
                    if (!$class->hasMethod($method)) {
                        continue;
                    }

                    $refMethod = $class->getMethod($method);
                    if (!$refMethod->isPublic() || (0 !== $refMethod->getNumberOfRequiredParameters())) {
                        continue;
                    }

                    $definition['getter'] = $method;
                    $definition['getter_is_method'] = true;
                }

                if (!isset($definition['getter'])) {
                    throw new \Exception("No public getter method was found for {$class->name}::{$element->name}.");
                }
            }

            // TODO Look for getter/setter
        } else {
            if (0 !== $element->getNumberOfRequiredParameters()) {
                throw new \Exception("The method {$class->name}::{$element->name}() is public but has required parameters.");
            } else {
                $definition['getter'] = $element->name;
                $definition['getter_is_method'] = true;
            }
        }

        return $definition;
    }

    /**
     * Checks whether the passed type is a primitive type
     *
     * @param  string  $type The type.
     *
     * @return boolean Return true if it is a primitive type, otherwise false.
     */
    private static function isPrimitiveType($type)
    {
        return in_array($type, array('array', 'string', 'float', 'double', 'boolean', 'bool', 'integer', '@id', 'void'));
    }

    /**
     * Converts a method name to a property name.
     *
     * @param  string $string Some string.
     *
     * @return string The property name version of the string.
     */
    private function propertirize($string)
    {
        $string = preg_replace_callback('/([a-z0-9])([A-Z])/', function ($match) { return $match[1] . '_' . strtolower($match[2]); }, $string);

        $prefix = substr($string, 0, strpos($string, '_'));
        $string = ('get' === $prefix) ? substr($string, 4) : $string;

        $suffix = substr($string, -4);

        return ('_iri' === $suffix) ? substr($string, 0, -4) : $string;
    }

    /**
     * Camelizes a given string.
     *
     * @param  string $string Some string.
     *
     * @return string The camelized version of the string.
     */
    private function camelize($string)
    {
        return preg_replace_callback('/(^|_|\.)+(.)/', function ($match) { return ('.' === $match[1] ? '_' : '').strtoupper($match[2]); }, $string);
    }
}
