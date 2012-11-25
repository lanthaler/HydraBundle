<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * Operation annotation
 *
 * The operation annotation is used to document the semantics of controller
 * methods. It allows to specify the data expected by the method. The data
 * it returns is documented by the "@return" annotation. It is also possible
 * to document the status codes ("status_codes") that may be returned.
 *
 * This annotation should not be confused with the {@link Operations}
 * annotation which is used to associate available operations to an element.
 *
 * @Annotation
 * @Target("METHOD")
 */
class Operation
{
    /**
     * @var string
     */
    public $expect = null;

    /**
     * @var array
     */
    public $status_codes = array();
}
