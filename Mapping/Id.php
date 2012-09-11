<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * @Annotation
 * @Target({"CLASS"})
 */
class Id
{
    /**
     * @var string
     */
    public $route;

    /**
     * @var array
     */
    public $variables = array();
}
