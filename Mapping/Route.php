<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * Route annotation
 *
 * The Route annotation is used to specify the "route" which is used to
 * transform the property's value or method's return value to an IRI. The
 * value is used to fill-in the variables of the route pattern. If there is
 * more than one variable, an array has to be returned. If the value is
 * false, the property will not be exposed when the object is serialized.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 */
class Route
{
    /**
     * @var string
     */
    public $route;
}
