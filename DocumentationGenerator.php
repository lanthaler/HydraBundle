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

        $this->documentType($documentation, 'ML\HydraBundle\Collection\Collection');

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            if ($method = $this->getReflectionMethod($route->getDefault('_controller'))) {
                if ($annotation = $this->getAnnotation($method, self::OPERATION_ANNOTATION)) {
                    $this->documentRoute($documentation, $name, $route, $annotation, $method);
                }
            }
        }

        return $documentation;
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

        $doc['expect'] = $annotation->expect;
        $doc['status_codes'] = $annotation->status_codes;

        $doc['return'] = $this->getType($method);

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


    public function getType(\Reflector $element)
    {
        $type = ($element instanceof \ReflectionMethod)
            ? $this->getReturnAnnotation($element)
            : $this->getVarAnnotation($element);

        $result = array('type' => $type, 'original_type' => $type);

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
}
