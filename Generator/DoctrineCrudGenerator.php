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
use Sensio\Bundle\GeneratorBundle\Generator\DoctrineCrudGenerator as SensioDoctrineCrudGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generates a CRUD controller.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class DoctrineCrudGenerator extends SensioDoctrineCrudGenerator
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
    public function generate(BundleInterface $bundle, $entity, ClassMetadataInfo $metadata, $format, $routePrefix, $needWriteActions, $forceOverwrite)
    {
        // Remove trailing "/"
        if ('/' === $routePrefix[strlen($routePrefix) - 1]) {
            $routePrefix = substr($routePrefix, 0, -1);
        }

        $this->routePrefix = $routePrefix;

        // Convert camel-cased class name to underscores
        $this->routeNamePrefix = strtolower(preg_replace_callback(
            '/([a-z0-9])([A-Z])/',
            function ($match) {
                return $match[1] . '_' . strtolower($match[2]);
            },
            substr($entity, strrpos($entity, '\\'))
        ));

        $this->actions = $needWriteActions
            ? array('collection_get', 'collection_post', 'entity_get', 'entity_put', 'entity_delete')
            : array('collection_get', 'entity_get');

        if (count($metadata->identifier) > 1) {
            throw new \RuntimeException('The CRUD generator does not support entity classes with multiple primary keys.');
        }

        if (!in_array('id', $metadata->identifier)) {
            throw new \RuntimeException('The CRUD generator expects the entity object has a primary key field named "id" with a getId() method.');
        }

        $this->entity   = $entity;
        $this->bundle   = $bundle;
        $this->metadata = $metadata;
        $this->format   = 'annotation';

        $this->generateControllerClass($forceOverwrite);
        $this->generateTestClass();
    }
}
