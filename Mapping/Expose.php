<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Mapping;


/**
 * Expose annotation
 *
 * The Expose annotation defines whether an element is exposed by the
 * serializer (or set by the deserializer). If an element is being exposed,
 * it is possible to define it's short name with the "as" parameter and it's
 * IRI (fragment) with the "iri" parameter. If the value of "iri" contains
 * a colon, it is assumed to represent the full, absolute IRI, otherwise it
 * is interpreted as IRI fragment which is appended to the vocabulary's IRI.
 * The three parameters "required", "readonly" and "writeonly" are used only
 * for properties.
 *
 * @Annotation
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class Expose
{
    /**
     * @var string
     */
    public $as = null;

    /**
     * @var string
     */
    public $iri = null;

    /**
     * @var boolean
     */
    public $required;

    /**
     * @var boolean
     */
    public $readonly;

    /**
     * @var boolean
     */
    public $writeonly;

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
        if (false !== strpos($this->iri, ':')) {
            return $this->iri;
        }

        if ('#' === $this->iri[0]) {
            return substr($this->iri, 1);
        }

        return $this->iri;
    }
}
