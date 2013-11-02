<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds all services with the tags "hydra.datatype_normalizer" as argument
 * to the "hydra.documentation_generator"
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class AddDatatypeNormalizerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('hydra.documentation_generator')) {
            return;
        }

        $normalizers = array();
        foreach ($container->findTaggedServiceIds('hydra.datatype_normalizer') as $serviceId => $attributes) {
            if (isset($attributes[0]['class'])) {
                $normalizers[$attributes[0]['class']] = new Reference($serviceId);
            }
        }

        $container->getDefinition('hydra.documentation_generator')->replaceArgument(4, $normalizers);
        $container->getDefinition('hydra.api')->replaceArgument(2, $normalizers);
    }
}
