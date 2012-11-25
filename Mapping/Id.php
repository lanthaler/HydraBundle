<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * Id annotation
 *
 * The Id annotation is used to specify the "route"  which is used to create
 * the identifier (an IRI) of an instance objects of the annotated class.
 * The optional "variables" parameter may be used to specify how route
 * variables are mapped to properties/methods of the object. *
 *
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
