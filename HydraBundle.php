<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use ML\HydraBundle\DependencyInjection\Compiler\AddDatatypeNormalizerPass;

/**
 * HydraBundle
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class HydraBundle extends Bundle
{
    /**
     * {@inheritDoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AddDatatypeNormalizerPass());
    }
}
