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
 * methods. It allows to specify the type of the operation and the expected
 * data. The type of data the operation returns is typically documented by
 * the "@return" annotation. It is also possible to document the status
 * codes ("status_codes") that may be returned.
 *
 * If "iri" is set, the annotation represents an externally defined
 * operation.
 *
 * This annotation should not be confused with the {@link Operations}
 * annotation which is used to associate available operations to an element.
 *
 * @Annotation
 * @Target("METHOD")
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class Operation
{
    /**
     * @var string
     */
    public $iri = null;

    /**
     * @var string
     */
    public $type = null;

    /**
     * @var string
     */
    public $expect = null;

    /**
     * @var array
     */
    public $status_codes = array();

    /**
     * Get the IRI (fragment)
     *
     * @return string The IRI fragment
     */
    public function getIri()
    {
        if (null === $this->iri) {
            return null;
        }

        // Is it an absolute IRI?
        if (strpos($this->iri, ':')) {
            return $this->iri;
        }

        if ('#' === $this->iri[0]) {
            return substr($this->iri, 1);
        }

        return $this->iri;
    }
}
