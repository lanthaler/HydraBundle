<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * HydraBundle Configuration
 *
 * Definition of the configuration settings available for the HydraBundle;
 * responsible for how the different settings are normalized, validated,
 * and merged.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('hydra');

// TODO Add API title, description, entrypoint, and global status code descriptions,
// perhaps also references to supported classes

        $rootNode
            ->fixXmlConfig('mapping')
            ->children()
                ->scalarNode('auto_mapping')->defaultValue(true)->end()
                ->scalarNode('naming_strategy')->defaultValue('hydra.naming_strategy.default')->end()
// FIXXME Do we need this!?
                // ->scalarNode('metadata_factory_class')
                //     ->defaultValue('ML\Hydra\Metadata\MetadataFactory')
                // ->end()
                ->arrayNode('metadata_cache_driver')
                    ->addDefaultsIfNotSet()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) { return array('type' => $v); })
                    ->end()
                    ->children()
                        ->scalarNode('type')->defaultValue('array')->end()
                        ->scalarNode('file_cache_dir')->defaultValue('%kernel.cache_dir%/hydra')->end()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->end()
                        ->scalarNode('instance_class')->end()
                        ->scalarNode('class')->end()
                        ->scalarNode('id')->end()
                    ->end()
                ->end()
                ->arrayNode('mappings')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function($v) { return array('type' => $v); })
                        ->end()
                        ->treatNullLike(array())
                        ->treatFalseLike(array('mapping' => false))
                        ->performNoDeepMerging()
                        ->children()
                            ->scalarNode('mapping')->defaultValue(true)->end()
                            ->scalarNode('type')->end()
                            ->scalarNode('dir')->end()
                            ->scalarNode('prefix')->end()
                            ->booleanNode('is_bundle')->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
