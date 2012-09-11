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
 */
class Expose
{
    /**
     * @var string
     */
    public $as = null;

    /**
     * @var bool
     */
    public $readonly = false;

    /**
     * @var bool
     */
    public $writeonly = false;
}
