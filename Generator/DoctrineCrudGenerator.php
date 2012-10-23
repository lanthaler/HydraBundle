<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class DoctrineCrudGenerator extends
    \Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator
{
    /**
     * Generate the CRUD controller.
     *
     * @param BundleInterface   $bundle           A bundle object
     * @param string            $entity           The entity relative class name
     * @param ClassMetadataInfo $metadata         The entity class metadata
     * @param string            $format           The configuration format (currently just annotation)
     * @param string            $routePrefix      The route name prefix
     * @param array             $needWriteActions Wether or not to generate write actions
     *
     * @throws \RuntimeException
     */
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $needWriteActions)
    {
        if ('/' === $routePrefix[strlen($routePrefix) - 1]) {
            $routePrefix = substr($routePrefix, 0, -1);
        }

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple primary keys.');
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException('The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.');
        }

        $parts = explode('\\', $entity);

        $this->entity   = $entity;
        $this->entityClass = array_pop($parts);
        $this->entityNamespace = implode('\\', $parts);
        $this->bundle   = $bundle;
        $this->metadata = $metadata;
        $this->format    = 'annotation';
        $this->routePrefix = $routePrefix;

        // Convert camel-cased class name to underscores
        $this->routeNamePrefix = strtolower(preg_replace_callback(
            '/([a-z0-9])([A-Z])/',
            function ($match) {
                return $match[1] . '_' . strtolower($match[2]);
            },
            $this->entityClass));

        $this->actions = $needWriteActions ?
            array('collection_get', 'collection_post', 'entity_get', 'entity_put', 'entity_delete')
            : array('collection_get', 'entity_get');

        $this->generateControllerClass();

        $this->generateTestClass();
    }

    /**
     * Generates the controller class only.
     *
     */
    private function generateControllerClass()
    {
        $dir = $this->bundle->getPath();

        $target = sprintf(
            '%s/Controller/%s/%sController.php',
            $dir,
            str_replace('\\', '/', $this->entityNamespace),
            $this->entityClass
        );

        if (file_exists($target)) {
            throw new \RuntimeException('Unable to generate the controller as it already exists.');
        }

        $this->renderFile($this->skeletonDir, 'controller.php', $target, array(
            'actions'           => $this->actions,
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'dir'               => $this->skeletonDir,
            'bundle'            => $this->bundle->getName(),
            'entity'            => $this->entity,
            'entity_class'      => $this->entityClass,
            'namespace'         => $this->bundle->getNamespace(),
            'entity_namespace'  => $this->entityNamespace,
            'format'            => $this->format,
        ));
    }

    /**
     * Generates the functional test class only.
     *
     */
    private function generateTestClass()
    {
        $dir    = $this->bundle->getPath() .'/Tests/Controller';
        $target = $dir .'/'. str_replace('\\', '/', $this->entityNamespace).'/'. $this->entityClass .'ControllerTest.php';

        $this->renderFile($this->skeletonDir, 'tests/test.php', $target, array(
            'route_prefix'      => $this->routePrefix,
            'route_name_prefix' => $this->routeNamePrefix,
            'entity'            => $this->entity,
            'entity_class'      => $this->entityClass,
            'namespace'         => $this->bundle->getNamespace(),
            'entity_namespace'  => $this->entityNamespace,
            'actions'           => $this->actions,
            'dir'               => $this->skeletonDir,
        ));
    }
}
