<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * Operations annotation
 *
 * The Operations annotation allows to associate a number of supported
 * operations to an element.
 *
 * This annotation should not be confused with the {@link Operation}
 * annotation which is used to document controller methods.
 *
 * @Annotation
 */
class Operations
{
    /**
     * @var array The available operations.
     */
    public $operations = array();
}
