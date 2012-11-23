<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\DatatypeNormalizer;

use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use ML\JsonLD\TypedValue;

/**
 * Converts XSD dateTime values into PHP DateTime objects and vice-versa
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class DateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * The supported format
     */
    const FORMAT = 'jsonld';

    /**
     * The XML Schema dateTime datatype IRI
     */
    const XSD_DATETIME_IRI = 'http://www.w3.org/2001/XMLSchema#dateTime';

    /**
     * The canonical XSD dateTime format (all times have to be in UTC)
     */
    const XSD_DATETIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Returns the IRI identifying the (data)type
     *
     * @return string The IRI identifying the (data)type
     */
    public function getTypeIri()
    {
        return self::XSD_DATETIME_IRI;
    }

    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object object to normalize
     * @param string $format format the normalization result will be encoded as
     *
     * @return string
     */
    public function normalize($object, $format = null)
    {
        $dt = clone $object;
        $dt->setTimezone(new \DateTimeZone('UTC'));

        return $object->format(self::XSD_DATETIME_FORMAT);
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer
     *
     * @param mixed  $data   Data to normalize.
     * @param string $format The format being (de-)serialized from or into.
     *
     * @return Boolean
     */
    public function supportsNormalization($data, $format = null)
    {
        return is_object($data) && ($data instanceof \DateTime) && (self::FORMAT === $format);
    }

    /**
     * Denormalizes data back into an object of the given class
     *
     * @param mixed  $data   data to restore
     * @param string $class  the expected class to instantiate
     * @param string $format format the given data was extracted from
     *
     * @return DateTime
     *
     * @throws RuntimeException If the data can't be denormalized
     */
    public function denormalize($data, $class, $format = null)
    {
        $value = $data;

        if (is_array($data)) {
            if (!isset($data['@value']) || !isset($data['@type'])) {
                throw new RuntimeException(
                    "Cannot denormalize the data as it isn't a valid JSON-LD typed value: " .
                    var_export($data, true)
                );
            }

            if (self::XSD_DATETIME_IRI !== $data['@type']) {
                throw new RuntimeException(
                    "Cannot denormalize the data as it isn't a XSD dateTime value: " .
                    var_export($data, true)
                );
            }

            $value = $data['@value'];
        } elseif (!is_string($data)) {
            throw new RuntimeException(
                "Cannot denormalize the data into a DateTime object: " .
                var_export($data, true)
            );
        }

        try {
            $date = new \DateTime($value);

            return $date;
        } catch(Exception $e) {
            throw new RuntimeException(
                "Cannot denormalize the data as the value is invalid: " . var_export($data, true),
                0,
                $e
            );
        }
    }

    /**
     * Checks whether the given class is supported for denormalization by this normalizer
     *
     * @param mixed  $data   Data to denormalize from.
     * @param string $type   The class to which the data should be denormalized.
     * @param string $format The format being deserialized from.
     *
     * @return Boolean
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        // TODO Check data?
        return ('DateTime' === $type) && (self::FORMAT === $format);
    }
}
