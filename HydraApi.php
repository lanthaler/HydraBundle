<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle;

use Symfony\Component\Routing\RouterInterface;
use ML\HydraBundle\Mapping\ClassMetadataFactory;

/**
 * A Hydra Web API
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class HydraApi
{
    /**
     * @var \Symfony\Component\Routing\RouterInterface The router
     */
    private $router;

    /**
     * @var string The vocabulary's absolute URL
     */
    private $vocabUrl;

    /**
     * @var string The title of the description of this class
     */
    private $title;

    /**
     * @var string A description of this class
     */
    private $description;

    /**
     * @var string The primary entry point of the API
     */
    private $entrypoint;

    /**
     * @var array Additional information about status codes that might be
     *            returned by the API
     */
    private $statusCodes;

    /**
     * @var \ML\HydraBundle\Mapping\ClassMetadataFactory
     */
    private $metadata;

    /**
     * @var array Mappings from scalar types to the corresponding XSD datatypes
     */
    private static $typeMap = array(
        'string' => 'http://www.w3.org/2001/XMLSchema#string',
        'integer' => 'http://www.w3.org/2001/XMLSchema#integer',
        'float' => 'http://www.w3.org/2001/XMLSchema#double',
        'double' => 'http://www.w3.org/2001/XMLSchema#double',
        'boolean' => 'http://www.w3.org/2001/XMLSchema#boolean',
        'bool' => 'http://www.w3.org/2001/XMLSchema#boolean',
        'void' => 'http://www.w3.org/2002/07/owl#Nothing',
        'mixed' => null
    );

    /**
     * Constructor
     *
     * @param RouterInterface      $router          The router.
     * @param ClassMetadataFactory $metadataFactory The metadata factory.
     * @param string               $vocabRoute      The route to use for
     *                                              vocabulary definitions.
     */
    public function __construct(RouterInterface $router, ClassMetadataFactory $metadataFactory, $normalizers, $vocabRoute = 'hydra_vocab')
    {
        $this->router = $router;
        $this->metadata = $metadataFactory;
        $this->normalizers = $normalizers;
        $this->vocabUrl = $this->router->generate($vocabRoute, array(), true);
    }

    /**
     * Gets the class metadata for the specified class
     *
     * @param string $className The name of the class.
     *
     * @return ClassMetadata The metadata.
     */
    public function getMetadataFor($className)
    {
        return $this->metadata->getMetadataFor($className);
    }

    /**
     * Exists a normalizer for the passed class?
     *
     * @param string $class The class for which a normalizer is required.
     *
     * @return boolean Returns true if a normalizer exists, false otherwise.
     */
    public function hasNormalizer($class)
    {
        if ('\\' === $class[0]) {
            $class = substr($class, 1);
        }

        return array_key_exists($class, $this->normalizers);
    }

    /**
     * Gets the normalizer for the passed class
     *
     * @param string $class The class whose normalizer should be retrieved
     *
     * @return object Returns the normalizer for the specified class
     */
    public function getNormalizer($class)
    {
        if ('\\' === $class[0]) {
            $class = substr($class, 1);
        }

        return $this->normalizers[$class];
    }

    /**
     * Gets the context corresponding to the passed class
     *
     * @param  string $exposedClassName The exposed class name
     *
     * @return array|null The context in the form of an associative array or
     *                    null if no class is exposed with the specified
     *                    name.
     */
    public function getContext($exposedClassName)
    {
        $classes = $this->metadata->getAllMetadata();
        $metadata = null;

        foreach ($classes as $class) {
            if ($class->getExposeAs() === $exposedClassName) {
                $metadata = $class;
                break;
            }
        }

        if (null === $metadata) {
            return null;
        }

        $context = array(
            'hydra' => 'http://www.w3.org/ns/hydra/core#',
            'vocab' => $this->vocabUrl . '#'
        );

        $context[$exposedClassName] = ($metadata->isExternalReference())
            ? $metadata->getIri()
            : 'vocab:' . $metadata->getIri();

        foreach ($metadata->getProperties() as $property) {
            // If something is exposed as keyword, no context definition is necessary
            if (0 === strncmp($property->getExposeAs(), '@', 1)) {
                // TODO Make this check more reliable by just checking for actual keywords
                //      What should we do if we serialize to RDFa for instance?
                continue;
            }

            $termDefinition = ($property->isExternalReference())
                ? $property->getIri()
                : 'vocab:' . $property->getIri();

            // TODO Make this check more robust
            if ($property->getRoute()) {
                $termDefinition = array('@id' => $termDefinition, '@type' => '@id');
            } elseif ($this->hasNormalizer($property->getType())) {
                $normalizer = $this->getNormalizer($property->getType());
                $termDefinition = array('@id' => $termDefinition, '@type' => $normalizer->getTypeIri());
            }

            $context[$property->getExposeAs()] = $termDefinition;
        }

        return array('@context' => $context);
    }

    /**
     * Get the URL of the Hydra ApiDocumentation
     *
     * @return string The URL of the Hydra ApiDocumentation
     */
    public function getDocumentationUrl()
    {
        return $this->vocabUrl;
    }

    /**
     * Get the Hydra ApiDocumentation
     *
     * @return array The Hydra ApiDocumentation in the form of an array
     *               ready to be serialized to JSON-LD
     */
    public function getDocumentation()
    {
        $metadata = $this->metadata->getAllMetadata();

        $docu = array(
            '@context' => array(
                'vocab' => $this->vocabUrl . '#',
                'hydra' => 'http://www.w3.org/ns/hydra/core#',
                'ApiDocumentation' => 'hydra:ApiDocumentation',
                'property' => array('@id' => 'hydra:property', '@type' => '@id'),
                'readonly' => 'hydra:readonly',
                'writeonly' => 'hydra:writeonly',
                'supportedClass' => 'hydra:supportedClass',
                'supportedProperty' => 'hydra:supportedProperty',
                'supportedOperation' => 'hydra:supportedOperation',
                'method' => 'hydra:method',
                'expects' =>  array('@id' => 'hydra:expects', '@type' => '@id'),
                'returns' =>  array('@id' => 'hydra:returns', '@type' => '@id'),
                'statusCodes' => 'hydra:statusCodes',
                'code' => 'hydra:statusCode',
                'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
                'label' => 'rdfs:label',
                'description' => 'rdfs:comment',
                'domain' => array('@id' => 'rdfs:domain', '@type' => '@id'),
                'range' =>  array('@id' => 'rdfs:range', '@type' => '@id'),
                'subClassOf' =>  array('@id' => 'rdfs:subClassOf', '@type' => '@id'),
            ),
            '@id' => $this->vocabUrl,
            '@type' => 'ApiDocumentation',
            'supportedClass' => array()
        );

        foreach ($metadata as $class) {
            if ($class->isExternalReference()) {
                $docu['supportedClass'][] = array(
                    '@id' => $class->getIri(),
                    '@type' => 'hydra:Class',
                    'hydra:title' => $class->getTitle(),
                    'hydra:description' => $class->getDescription(),
                    'supportedOperation' => $this->documentOperations($class->getOperations()),
                    'supportedProperty' => $this->documentClassProperties($class),
                );
            } else {
                if (false !== ($superclass = get_parent_class($class->getName()))) {
                    try {
                        $superclass = $this->metadata->getMetadataFor($superclass);

                        $superclass = $superclass->isExternalReference()
                            ? $superclass->getIri()
                            : 'vocab:' . $superclass->getIri();
                    } catch (\Exception $e) {
                        $superclass = null;
                    }
                } else {
                    $superclass = null;
                }

                $docu['supportedClass'][] = array(
                    '@id' => 'vocab:' . $class->getIri(),
                    '@type' => 'hydra:Class',
                    'subClassOf' => $superclass,
                    'label' => $class->getTitle(),
                    'description' => $class->getDescription(),
                    'supportedOperation' => $this->documentOperations($class->getOperations()),
                    'supportedProperty' => $this->documentClassProperties($class),
                );
            }

        }

        return $docu;
    }

    /**
     * Creates a JSON-LD serialization of the passed operations
     *
     * @param array|null $operations The operations
     *
     * @return array|null The JSON-LD serialization of the operations
     */
    private function documentOperations($operations)
    {
        if (null === $operations) {
            return null;
        }

        $result = array();

        foreach ($operations as $operation) {
            $statusCodes = array();
            foreach ($operation->getStatusCodes() as $code => $description) {
                $statusCodes[] = array(
                    'code' => $code,
                    'description' => $description
                );
            }

            $result[] = array(
                '@id' => '_:' . $operation->getName(),
                'method' => $operation->getMethod(),
                'label' => ($operation->getTitle())
                    ?
                    : $operation->getDescription(),
                'description' => (null === $operation->getTitle())
                    ? null
                    : $operation->getDescription(),
                'expects' => $this->getTypeReferenceIri($operation->getExpects()),
                'returns' => $this->getTypeReferenceIri($operation->getReturns()),
                'statusCodes' => $statusCodes
            );
        }

        return $result;
    }

    /**
     * Documents the properties of the passed ClassMetadata object
     *
     * @param ML\HydraBundle\Mapping\ClassMetadata $class The class metadata
     *
     * @return array The JSON-LD serialization of the class' properties.
     */
    private function documentClassProperties(\ML\HydraBundle\Mapping\ClassMetadata $class)
    {
        $result = array();
        $propertyDomain = $this->getTypeReferenceIri($class->getName());

        foreach ($class->getProperties() as $property) {
            $result[] = array(
                'property' => ($property->isExternalReference())
                    ? $property->getIri()
                    : array(
                        '@id' => 'vocab:' . $property->getIri(),
                        '@type' => ($property->getRoute())
                            ? 'hydra:Link'
                            : 'rdf:Property',
                        'label' => $property->getTitle(),
                        'description' => $property->getDescription(),
                        'domain' => $propertyDomain,
                        'range' => $this->getTypeReferenceIri($property->getType()),
                        'supportedOperation' => $this->documentOperations($property->getOperations())
                    ),
                'hydra:title' => $property->getTitle(),
                'hydra:description' => $property->getDescription(),
                'required' => $property->getRequired(),
                'readonly' => $property->isReadOnly(),
                'writeonly' => $property->isWriteOnly()
            );
        }

        return $result;
    }

    /**
     * Get the (compact) IRI to reference the specified type
     *
     * @param string|null $type The type to reference.
     *
     * @return string|null The IRI corresponding to the type.
     */
    private function getTypeReferenceIri($type)
    {
        if (null === $type) {
            return null;
        }

        if (array_key_exists($type, self::$typeMap)) {
            return self::$typeMap[$type];
        }

        if ($this->hasNormalizer($type)) {
            return $this->getNormalizer($type)->getTypeIri();
        }

        $metadata = $this->metadata->getMetadataFor($type);

        if (null === $metadata) {
            // TODO Improve this
            throw new \Exception('Found invalid type: ' . $type);
        }

        if ($metadata->isExternalReference()) {
            return $metadata->getIri();
        } else {
            return 'vocab:' . $metadata->getIri();
        }
    }
}
