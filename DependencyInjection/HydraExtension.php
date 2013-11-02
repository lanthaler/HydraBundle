<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\Resource\FileResource;

/**
 * HydraExtension
 *
 * Manages the dependency injection container configuration of the
 * HydraBundle.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class HydraExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $config);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $this->loadMappingInformation($config, $container);
        $this->loadCacheDriver($config, $container);
    }

    /**
     * Loads mapping information
     *
     * There are two distinct configuration possibilities for mapping
     * information:
     *
     * 1. Specify a bundle and optionally details where the entity or
     *    mapping information reside.
     * 2. Specify an arbitrary mapping location.
     *
     * <code>
     *  hydra:
     *     mappings:
     *         MyBundle1: ~
     *         MyBundle2: yml
     *         MyBundle3: { type: annotation, dir: Entities/ }
     *         MyBundle4: { type: xml, dir: Resources/config/hydra/mapping }
     *         MyBundle5:
     *             type: yml
     *             dir: [ bundle-mappings1/, bundle-mappings2/ ]
     *         arbitrary_key:
     *             type: xml
     *             dir: %kernel.dir%/../src/vendor/ApiExtensions/config/
     *             prefix: ApiExtensions\Entities\
     * </code>
     *
     * In the case of bundles everything is really optional (the missing
     * parts can be auto-detected), otherwise everything is a required
     * argument.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException
     */
    protected function loadMappingInformation(array $config, ContainerBuilder $container)
    {
// FIXXME Remove $this->drivers if possible
        // reset state of drivers map. They are only used by this methods and children.
        $this->drivers = array();

        if ($config['auto_mapping']) {
            // automatically register bundle mappings
            foreach (array_keys($container->getParameter('kernel.bundles')) as $bundle) {
                if (!isset($config['mappings'][$bundle])) {
                    $config['mappings'][$bundle] = array(
                        'mapping'   => true,
                        'is_bundle' => true,
                    );
                }
            }
        }

        $container->setAlias('hydra.naming_strategy', new Alias($config['naming_strategy'], false));

        foreach ($config['mappings'] as $mappingName => $mappingConfig) {
            if (null !== $mappingConfig && false === $mappingConfig['mapping']) {
                continue;
            }

            $mappingConfig = array_replace(array(
                'dir'    => false,
                'type'   => false,
                'prefix' => false,
            ), (array) $mappingConfig);

            $mappingConfig['dir'] = $container->getParameterBag()->resolveValue($mappingConfig['dir']);

            // a bundle configuration is detected by realizing that the specified dir is not absolute and existing
            if (!isset($mappingConfig['is_bundle'])) {
                $mappingConfig['is_bundle'] = !is_dir($mappingConfig['dir']);
            }

            if ($mappingConfig['is_bundle']) {
                $bundle = null;
                foreach ($container->getParameter('kernel.bundles') as $name => $class) {
                    if ($mappingName === $name) {
                        $bundle = new \ReflectionClass($class);

                        break;
                    }
                }

                if (null === $bundle) {
                    throw new \InvalidArgumentException(sprintf('Bundle "%s" does not exist or it is not enabled.', $mappingName));
                }

                $mappingConfig = $this->getMappingDriverBundleConfigDefaults($mappingConfig, $bundle, $container);
                if (!$mappingConfig) {
                    continue;
                }
            }
            $this->validateMappingConfiguration($mappingConfig, $mappingName);
            $this->setMappingDriverConfig($mappingConfig, $mappingName);
        }


        $this->registerMappingDrivers($config, $container);
    }

    /**
     * If this is a bundle controlled mapping all the missing information can be autodetected by this method.
     *
     * Returns false when autodetection failed, an array of the completed information otherwise.
     *
     * @param array            $bundleConfig
     * @param \ReflectionClass $bundle
     * @param ContainerBuilder $container    A ContainerBuilder instance
     *
     * @return array|false
     */
    protected function getMappingDriverBundleConfigDefaults(array $bundleConfig, \ReflectionClass $bundle, ContainerBuilder $container)
    {
        $bundleDir = dirname($bundle->getFilename());

        if (!$bundleConfig['type']) {
            $bundleConfig['type'] = $this->detectMetadataDriver($bundleDir, $container);
        }

        if (!$bundleConfig['type']) {
            // skip this bundle, no mapping information was found.
            return false;
        }

        if (!$bundleConfig['dir']) {
            if (in_array($bundleConfig['type'], array('annotation', 'staticphp'))) {
                $bundleConfig['dir'] = $bundleDir.'/'.$this->getMappingObjectDefaultName();
            } else {
                $bundleConfig['dir'] = $bundleDir.'/'.$this->getMappingResourceConfigDirectory();
            }
        } else {
            $bundleConfig['dir'] = $bundleDir.'/'.$bundleConfig['dir'];
        }

        if (!$bundleConfig['prefix']) {
            $bundleConfig['prefix'] = $bundle->getNamespaceName().'\\'.$this->getMappingObjectDefaultName();
        }

        return $bundleConfig;
    }

    /**
     * Detects what metadata driver to use for the supplied directory.
     *
     * @param string           $dir       A directory path
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return string|null A metadata driver short name, if one can be detected
     */
    protected function detectMetadataDriver($dir, ContainerBuilder $container)
    {
        // add the closest existing directory as a resource
        $configPath = $this->getMappingResourceConfigDirectory();
        $resource = $dir.'/'.$configPath;
        while (!is_dir($resource)) {
            $resource = dirname($resource);
        }

        $container->addResource(new FileResource($resource));

        $extension = $this->getMappingResourceExtension();
        if (($files = glob($dir.'/'.$configPath.'/*.'.$extension.'.xml')) && count($files)) {
            return 'xml';
        } elseif (($files = glob($dir.'/'.$configPath.'/*.'.$extension.'.yml')) && count($files)) {
            return 'yml';
        } elseif (($files = glob($dir.'/'.$configPath.'/*.'.$extension.'.php')) && count($files)) {
            return 'php';
        }

        // add the directory itself as a resource
        $container->addResource(new FileResource($dir));

        if (is_dir($dir.'/'.$this->getMappingObjectDefaultName())) {
            return 'annotation';
        }

        return null;
    }

    /**
     * Noun that describes the mapped objects such as Entity or Controller.
     *
     * Will be used for auto-detection of persistent objects directory.
     *
     * @return string
     */
    protected function getMappingObjectDefaultName()
    {
        return 'Entity';
    }

    /**
     * Relative path from the bundle root to the directory where mapping files reside.
     *
     * @return string
     */
    protected function getMappingResourceConfigDirectory()
    {
        return 'Resources/config/hydra';
    }

    /**
     * Extension used by the mapping files.
     *
     * @return string
     */
    protected function getMappingResourceExtension()
    {
        return 'hydra';
    }

    /**
     * Register the mapping driver configuration for later use with the object managers metadata driver chain.
     *
     * @param array  $mappingConfig
     * @param string $mappingName
     *
     * @throws \InvalidArgumentException
     */
    protected function setMappingDriverConfig(array $mappingConfig, $mappingName)
    {
        if (is_dir($mappingConfig['dir'])) {
            $this->drivers[$mappingConfig['type']][$mappingConfig['prefix']] = realpath($mappingConfig['dir']);
        } else {
            throw new \InvalidArgumentException(sprintf('Invalid Hydra mapping path given. Cannot load Hydra mapping/bundle named "%s".', $mappingName));
        }
    }

    /**
     * Register all the collected mapping information with the object manager by registering the appropriate mapping drivers.
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    protected function registerMappingDrivers($config, ContainerBuilder $container)
    {
        // configure metadata driver for each bundle based on the type of mapping files found
        if ($container->hasDefinition('hydra.metadata_driver')) {
            $chainDriverDef = $container->getDefinition('hydra.metadata_driver');
        } else {
            $chainDriverDef = new Definition('%hydra.metadata.driver_chain.class%');
            $chainDriverDef->setPublic(false);
        }

        foreach ($this->drivers as $driverType => $driverPaths) {
            $mappingService = 'hydra.'.$driverType.'_metadata_driver';
            if ($container->hasDefinition($mappingService)) {
                $mappingDriverDef = $container->getDefinition($mappingService);
                $args = $mappingDriverDef->getArguments();
                if ($driverType == 'annotation') {
                    $args[1] = array_merge(array_values($driverPaths), $args[1]);
                } else {
                    $args[0] = array_merge(array_values($driverPaths), $args[0]);
                }
                $mappingDriverDef->setArguments($args);
            } elseif ($driverType == 'annotation') {
                $mappingDriverDef = new Definition('%hydra.metadata.annotation.class%', array(
                    new Reference('hydra.metadata.annotation_reader'),
                    array_values($driverPaths),
                    new Reference('router')
                ));
            } else {
                $mappingDriverDef = new Definition('%hydra.metadata.'.$driverType.'.class%', array(
                    array_values($driverPaths)
                ));
            }
            $mappingDriverDef->setPublic(false);
            if (false !== strpos($mappingDriverDef->getClass(), 'yml') || false !== strpos($mappingDriverDef->getClass(), 'xml')) {
                $mappingDriverDef->setArguments(array(array_flip($driverPaths)));
                $mappingDriverDef->addMethodCall('setGlobalBasename', array('mapping'));
            }

            $container->setDefinition($mappingService, $mappingDriverDef);

            foreach ($driverPaths as $prefix => $driverPath) {
                $chainDriverDef->addMethodCall('addDriver', array(new Reference($mappingService), $prefix));
            }
        }

        $container->setDefinition('hydra.metadata_driver', $chainDriverDef);
    }

    /**
     * Validates the the specified mapping information
     *
     * @param array  $mappingConfig
     * @param string $mappingName
     *
     * @throws \InvalidArgumentException
     */
    protected function validateMappingConfiguration(array $mappingConfig, $mappingName)
    {
        if (!$mappingConfig['type'] || !$mappingConfig['dir'] || !$mappingConfig['prefix']) {
            throw new \InvalidArgumentException(
                sprintf('Hydra mapping definitions for "%s" require at least the "type", "dir" and "prefix" options.', $mappingName)
            );
        }

        if (!is_dir($mappingConfig['dir'])) {
            throw new \InvalidArgumentException(
                sprintf('Specified non-existing directory "%s" as Hydra mapping source.', $mappingConfig['dir'])
            );
        }

        if (!in_array($mappingConfig['type'], array('xml', 'yml', 'annotation', 'php', 'staticphp'))) {
            // FIXXME Make sure hydra.metadata_driver exists
            throw new \InvalidArgumentException(
                'Can only configure "xml", "yml", "annotation", "php" or '.
                '"staticphp" through the HydraBundle. Use your own bundle to configure other metadata drivers. '.
                'You can register them by adding a new driver to the '.
                '"hydra.metadata_driver" service definition.'
            );
        }
    }

    /**
     * Loads a metadata cache driver
     *
     * @param array            $config    An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException In case of unknown driver type.
     */
    protected function loadCacheDriver(array $config, ContainerBuilder $container)
    {
        $cacheDriver = $config['metadata_cache_driver'];
        $cacheDriverService = 'hydra.metadata_cache';

        switch ($cacheDriver['type']) {
            case 'service':
                $container->setAlias($cacheDriverService, new Alias($cacheDriver['id'], false));

                return;
            case 'file':
                $cacheDir = $container->getParameterBag()->resolveValue($cacheDriver['file_cache_dir']);

                if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true)) {
                    throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
                }

                $cacheDef = new Definition('%hydra.cache.file.class%', array($cacheDir, '.hydracache.php'));
                break;
            case 'memcache':
                $memcacheClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%hydra.cache.memcache.class%';
                $memcacheInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%hydra.cache.memcache_instance.class%';
                $memcacheHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%hydra.cache.memcache_host%';
                $memcachePort = !empty($cacheDriver['port']) || (isset($cacheDriver['port']) && $cacheDriver['port'] === 0)  ? $cacheDriver['port'] : '%hydra.cache.memcache_port%';
                $cacheDef = new Definition($memcacheClass);
                $memcacheInstance = new Definition($memcacheInstanceClass);
                $memcacheInstance->addMethodCall('connect', array(
                    $memcacheHost, $memcachePort
                ));
                $container->setDefinition(sprintf('hydra.%s_memcache_instance', $config['name']), $memcacheInstance);
                $cacheDef->addMethodCall('setMemcache', array(new Reference(sprintf('hydra.%s_memcache_instance', $config['name']))));
                break;
            case 'memcached':
                $memcachedClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%hydra.cache.memcached.class%';
                $memcachedInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%hydra.cache.memcached_instance.class%';
                $memcachedHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%hydra.cache.memcached_host%';
                $memcachedPort = !empty($cacheDriver['port']) ? $cacheDriver['port'] : '%hydra.cache.memcached_porthydra.%';
                $cacheDef = new Definition($memcachedClass);
                $memcachedInstance = new Definition($memcachedInstanceClass);
                $memcachedInstance->addMethodCall('addServer', array(
                    $memcachedHost, $memcachedPort
                ));
                $container->setDefinition(sprintf('hydra.%s_memcached_instance', $config['name']), $memcachedInstance);
                $cacheDef->addMethodCall('setMemcached', array(new Reference(sprintf('hydra.%s_memcached_instance', $config['name']))));
                break;
            case 'redis':
                $redisClass = !empty($cacheDriver['class']) ? $cacheDriver['class'] : '%hydra.cache.redis.class%';
                $redisInstanceClass = !empty($cacheDriver['instance_class']) ? $cacheDriver['instance_class'] : '%hydra.cache.redis_instance.class%';
                $redisHost = !empty($cacheDriver['host']) ? $cacheDriver['host'] : '%hydra.cache.redis_host%';
                $redisPort = !empty($cacheDriver['port']) ? $cacheDriver['port'] : '%hydra.cache.redis_port%';
                $cacheDef = new Definition($redisClass);
                $redisInstance = new Definition($redisInstanceClass);
                $redisInstance->addMethodCall('connect', array(
                    $redisHost, $redisPort
                ));
                $container->setDefinition(sprintf('hydra.%s_redis_instance', $config['name']), $redisInstance);
                $cacheDef->addMethodCall('setRedis', array(new Reference(sprintf('hydra.%s_redis_instance', $config['name']))));
                break;
            case 'apc':
            case 'array':
            case 'xcache':
            case 'wincache':
            case 'zenddata':
                $cacheDef = new Definition('%hydra.'.sprintf('cache.%s.class', $cacheDriver['type']).'%');
                break;
            default:
                throw new \InvalidArgumentException(sprintf('"%s" is an unrecognized Hydra cache driver.', $cacheDriver['type']));
        }

        $cacheDef->setPublic(false);
        // generate a unique namespace for the given application
        $namespace = 'sf2'.$this->getMappingResourceExtension().md5($container->getParameter('kernel.root_dir').$container->getParameter('kernel.environment'));
        $cacheDef->addMethodCall('setNamespace', array($namespace));

        $container->setDefinition($cacheDriverService, $cacheDef);
    }
}
