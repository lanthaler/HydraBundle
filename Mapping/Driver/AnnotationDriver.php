<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping\Driver;

use Reflector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use ML\HydraBundle\Mapping\ClassMetadata;
use ML\HydraBundle\Mapping\PropertyDefinition;
use ML\HydraBundle\Mapping\OperationDefinition;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\Common\Annotations\Reader;

/**
 * The Hydra AnnotationDriver
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class AnnotationDriver implements MappingDriver
{
    /**
     * The paths where to look for mapping files.
     *
     * @var array
     */
    protected $paths = array();

    /**
     * The file extension of mapping documents.
     *
     * @var string
     */
    protected $fileExtension = '.php';

    /**
     * Cache for AnnotationDriver#getAllClassNames()
     *
     * @var array
     */
    protected $classNames;

    /**
     * @var array<OperationDefintion> Already generated route metadata
     */
    private $routeMetadata = array();

    /**
     * Constructor
     *
     * Initializes a new AnnotationDriver that uses the given annotation
     * reader for reading docblock annotations.
     *
     * @param Reader $reader The annotation reader to use.
     * @param string|array $paths One or multiple paths where mapping
     *                            classes can be found.
     */
    public function __construct(Reader $reader, $paths = null, RouterInterface $router = null)
    {
        $this->reader = $reader;
        if ($paths) {
            $this->addPaths((array) $paths);
        }

        // FIXXME This member variable doesn't exist, we shouldn't inject the router interface directly
        $this->router = $router;
    }

    /**
     * Append lookup paths to metadata driver.
     *
     * @param array $paths
     */
    public function addPaths(array $paths)
    {
        $this->paths = array_unique(array_merge($this->paths, $paths));
    }

    /**
     * Retrieve the defined metadata lookup paths.
     *
     * @return array
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Retrieve the current annotation reader
     *
     * @return AnnotationReader
     */
    public function getReader()
    {
        return $this->reader;
    }

    /**
     * Set the file extension used to look for mapping files under
     *
     * @param string $fileExtension The file extension to set
     * @return void
     */
    public function setFileExtension($fileExtension)
    {
        $this->fileExtension = $fileExtension;
    }

    /**
     * Get the file extension used to look for mapping files under
     *
     * @return string
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        if ($this->classNames !== null) {
            return $this->classNames;
        }

        if ( ! $this->paths) {
            throw MappingException::pathRequired();
        }

        $classes = array();
        $includedFiles = array();

        foreach ($this->paths as $path) {
            if ( ! is_dir($path)) {
                throw MappingException::fileMappingDriversRequireConfiguredDirectoryPath($path);
            }

            $iterator = new \RegexIterator(
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                ),
                '/^.+' . preg_quote($this->fileExtension) . '$/i',
                \RecursiveRegexIterator::GET_MATCH
            );

            foreach ($iterator as $file) {
                $sourceFile = realpath($file[0]);

                require_once $sourceFile;

                $includedFiles[] = $sourceFile;
            }
        }

        $declared = get_declared_classes();

        foreach ($declared as $className) {
            $rc = new ReflectionClass($className);
            $sourceFile = $rc->getFileName();
            if (in_array($sourceFile, $includedFiles) && $this->isExposed($className)) {
                $classes[] = $className;
            }
        }

        $this->classNames = $classes;

        return $classes;
    }

    /**
     * {@inheritDoc}
     */
    public function isExposed($className)
    {
        $annotation = $this->reader->getClassAnnotation(
            new ReflectionClass($className),
            'ML\HydraBundle\Mapping\Expose'
        );

        return null !== $annotation;
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($className)
    {
        $class = new ReflectionClass($className);
        $classAnnotations = $this->reader->getClassAnnotations($class);

        if ($classAnnotations) {
            foreach ($classAnnotations as $key => $annot) {
                if ( ! is_numeric($key)) {
                    continue;
                }

                $classAnnotations[get_class($annot)] = $annot;
            }
        }

        if (!isset($classAnnotations['ML\HydraBundle\Mapping\Expose'])) {
            return null;
        }

        $annotation = $classAnnotations['ML\HydraBundle\Mapping\Expose'];

        $metadata = new ClassMetadata($className);
        $metadata->setExposeAs($annotation->as);
        $metadata->setIri($annotation->getIri());

        $this->documentRouteAndOperations($metadata, $class);
        $this->documentProperties($metadata, $class);


        // If this class represents an external definition, we don't need to
        // collect all data; just the one necessary for the serializer
        if ($metadata->isExternalReference()) {
            return $metadata;
        }

        $docu = $this->getDocBlockText($class);

        $metadata->setTitle($docu['title']);
        $metadata->setDescription($docu['description']);

        return $metadata;
    }

    /**
     * Document the route and operations associated to an element
     *
     * @param Reflector $element The element being processed.
     */
    private function documentRouteAndOperations($metadata, Reflector $element)
    {
        if ((null !== ($annotation = $this->getAnnotation($element, 'ML\HydraBundle\Mapping\Id'))) ||
            (null !== ($annotation = $this->getAnnotation($element, 'ML\HydraBundle\Mapping\Route')))) {
            // TODO Check that the IRI template can be filled!?
            $metadata->setRoute($this->getRouteMetadata($annotation->route));
        }

        $annotation = $this->getAnnotation($element, 'ML\HydraBundle\Mapping\Operations');
        if (null !== $annotation) {
            $operations = array_unique($annotation->operations);

            $operationsMetadata = array_map(array($this, 'getRouteMetadata'), $operations);

            $metadata->setOperations($operationsMetadata);
        }

        if (null !== ($route = $metadata->getRoute())) {
            // Add the route to the supported operations
            $metadata->addOperation($this->getRouteMetadata($route->getName()));
        } elseif (null !== $annotation) {
            // ... or use an operation as route if none is set

            // FIXXME: Do this only for GET operations!
            $metadata->setRoute($this->getRouteMetadata(reset($annotation->operations)));
        }

        if (($metadata instanceof PropertyDefinition) && (count($operations = $metadata->getOperations()) > 0)) {
            foreach ($operations as $operation) {
                if (('GET' === $operation->getMethod()) && (null !== $operation->getReturns())) {
                    $metadata->setType($operation->getReturns());
                    return;
                }
            }

            $metadata->setType('ML\HydraBundle\Entity\Resource');
        }
    }

    /**
     * Get information about a route
     *
     * @param string $routeName
     */
    protected function getRouteMetadata($routeName)
    {
        if (isset($this->routeMetadata[$routeName])) {
            return $this->routeMetadata[$routeName];
        }

        $route = $this->router->getRouteCollection()->get($routeName);

        if (null === $route) {
            // TODO Improve this
            throw new \Exception(sprintf('The route "%s" couldn\'t be found', $routeName));
        }

        // TODO Check this
        $controller = $route->getDefault('_controller');
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
                $method = new ReflectionMethod($class, $method);
            } catch (\ReflectionException $e) {
                throw new \Exception(sprintf('The controller method for the route "%s" couldn\'t be found', $routeName));
            }
        }

        if (null === ($annotation = $this->getAnnotation($method, 'ML\HydraBundle\Mapping\Operation'))) {
            throw new \Exception(sprintf('The controller method for the route "%s" is not marked as an operation', $routeName));
        }

        $operation = new OperationDefinition($routeName);
        $operation->setIri($annotation->getIri());
        $operation->setType($annotation->type);

        $operation->setRoute($route);

        $tmp = $this->getDocBlockText($method);

        $operation->setTitle($tmp['title']);
        $operation->setDescription($tmp['description']);

        $operation->setExpects($annotation->expect);
        $operation->setStatusCodes($annotation->status_codes);

        // TODO Check this
        $tmp = $this->getType($method);
        $operation->setReturns($tmp['type']);

        // TODO Check this! Should we use the return type instead?
        if ($tmp['is_array'] || (null !== ($annotation = $this->getAnnotation($method, 'ML\HydraBundle\Mapping\Collection')))) {
            $operation->setReturns('ML\HydraBundle\Entity\Collection');
        }

        // if (('array' === $operation['return']['type']) || (self::HYDRA_COLLECTION === $operation['return']['type'])) {
        //     $operation['return']['type'] = self::HYDRA_COLLECTION;
        // }

        // method/IRI
        $operation->setMethod($route->getRequirement('_method'));
        // $operation['path'] = $route->getPath();
        // $operation['variables'] = $route->compile()->getVariables();
        // $operation['defaults'] = $route->getDefaults();
        // unset($operation['defaults']['_controller']);

        // Cache the metadata since it might be needed several times
        $this->routeMetadata[$routeName] = $operation;

        return $operation;
    }

    /**
     * Document the properties and methods associated to a class
     *
     * @param ClassMetadata $metadata The class definition
     * @param ReflectionClass $class    The class whose properties and
     *                                  methods should be documented.
     */
    private function documentProperties(ClassMetadata $metadata, ReflectionClass $class)
    {
/*

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
*/

        $properties = array();
        $elements = array_merge($class->getProperties(), $class->getMethods());

        foreach ($elements as $element) {
            $annotation = $this->getAnnotation($element, 'ML\HydraBundle\Mapping\Expose');

            if (null === $annotation) {
                continue;
            }

            // $exposeAs = $element->name;
            // if ($annotation->as) {
            //     $exposeAs = $annotation->as;

            //     if ($annotation->getIri()) {
            //         $property['iri'] = $annotation->getIri();
            //     } else {
            //         $property['iri'] = $exposeClassAs . '/' . $exposeAs;
            //     }
            // } else {
            //     $exposeAs = $this->propertirize($exposeAs);

            //     if ($annotation->getIri()) {
            //         $property['iri'] = $annotation->getIri();
            //     } else {
            //         $property['iri'] = $this->camelize($exposeAs);
            //         $property['iri'][0] = strtolower($property['iri'][0]);
            //         $property['iri'] =  $exposeClassAs . '/' . $property['iri'];
            //     }
            // }

            $property = new PropertyDefinition($class->name, $element->name);
            $property->setExposeAs($annotation->as);
            $property->setIri($annotation->getIri());

            if (null !== $annotation->required) {
                $property->setRequired($annotation->required);
            }
            if (null !== $annotation->readonly) {
                $property->setReadOnly($annotation->readonly);
            }
            if (null !== $annotation->writeonly) {
                $property->setWriteOnly($annotation->writeonly);
            }

            $tmp = $this->getDocBlockText($element);
            $property->setTitle($tmp['title']);
            $property->setDescription($tmp['description']);

            $tmp = $this->getType($element);
            $property->setType($tmp['type']);

            $this->documentRouteAndOperations($property, $element);

            if (null !== ($annotation = $this->getAnnotation($element, 'ML\HydraBundle\Mapping\Collection'))) {
                // TODO Check for conflicting routes!?
                // TODO Check that the IRI template can be filled!?
                $property->setRoute($this->getRouteMetadata($annotation->route));

                if (false === $property->supportsOperation($annotation->route)) {
                    $property->addOperation($this->getRouteMetadata($annotation->route));
                }

                $property->setType('ML\HydraBundle\Entity\Collection');
                $property->setReadOnly(true);
            }

/*
            if ($element instanceof ReflectionMethod) {
                if (array_key_exists($element->name, $linkRelationMethods)) {
                    $property['original_type'] .= ' --- ' . $linkRelationMethods[$element->name] . '::' . $element->name;
                }
            }
*/
            // TODO Validate definition, this here isn't the right place to do so, create a metadata factory

            $properties[] = $property;
        }

        // $documentation['class2type'][$class->name] = $exposeClassAs;
        // $documentation['types'][$exposeClassAs] = $result;


        $metadata->setProperties($properties);
    }

    /**
     * Get the specified annotation of an element
     *
     * @param  Reflector $element    The element whose annotation should be
     *                               retrieved
     * @param  string    $annotation The class of the annotation to retrieve
     *
     * @return object|null The annotation or null if the element doesn't
     *                     have the specified annotation
     */
    private function getAnnotation(Reflector $element, $annotation)
    {
        if ($element instanceof ReflectionClass) {
            return $this->reader->getClassAnnotation($element, $annotation);
        } elseif ($element instanceof ReflectionMethod) {
            return $this->reader->getMethodAnnotation($element, $annotation);
        } elseif ($element instanceof ReflectionProperty) {
            return $this->reader->getPropertyAnnotation($element, $annotation);
        }

        return null;
    }

    protected function getDocBlockText(Reflector $element)
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

            return array('title' => $parts[0], 'description' => $parts[1]);
        } elseif ('' !== $parts[0]) {
            return array('title' => null, 'description' => $parts[0]);
        } else {
            return array('title' => null, 'description' => null);
        }

    }

    protected function getType(Reflector $element)
    {
        $type = ($element instanceof ReflectionMethod)
            ? $this->getReturnAnnotation($element)
            : $this->getVarAnnotation($element);

        $result = array('type' => $type, 'original_type' => $type, 'is_array' => false);

        if (null === $type) {
            return $result;
        }

        // Checks if the property has @var array array<type> or type[] annotation
        if ('array' === $type) {
            // unclassified arrays are interpreted as being untyped
            $result['type'] = null;
            $result['is_array'] = true;
        } elseif ($type && false !== $pos = strpos($type, '<')) {
            $result['type'] = substr($type, $pos + 1, -1);
            $result['is_array'] = true;
        } elseif ('[]' === substr($type, -2)) {
            $result['type'] = substr($type, 0, -2);
            $result['is_array'] = true;
        }

        return $result;
    }

    /**
     * Extracts the type of a property using the @var annotation
     *
     * @param  ReflectionProperty $property The property.
     * @return array The property's type.
     */
    protected function getVarAnnotation(ReflectionProperty $property)
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

    protected function getReturnAnnotation(ReflectionMethod $method)
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
}
