<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ML\HydraBundle\Serializer\Serializer;
use ML\HydraBundle\JsonLdResponse;

/**
 * HydraController is a implementation of a Controller for Hydra
 *
 * It provides methods to common features needed in Hydra controllers.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class HydraController extends Controller
{
    /**
     * The JSON-LD format
     */
    const FORMAT = 'jsonld';

    /**
     * Shortcut to serialize an entity into JSON-LD
     *
     * This method uses the Hydra Serializer service and therefore requires
     * that the HydraBundle is registered.
     *
     * @param object $entity The entity to serialize
     *
     * @return string The entity serialized in JSON-LD
     *
     * @throws \LogicException If HydraBundle is not available
     */
    public function serialize($entity)
    {
        if (!$this->container->has('hydra.serializer')) {
            throw new \LogicException('The HydraBundle is not registered in your application.');
        }

        return $this->container->get('hydra.serializer')->serialize($entity, self::FORMAT);
    }

    /**
     * Shortcut to deserialize JSON-LD data to an entity
     *
     * This method uses the Hydra Serializer service and therefore requires
     * that the HydraBundle is registered.
     *
     * @param string        $data   The data to deserialize
     * @param string|object $entity The class or an instance thereof the data
     *                              should be deserialized to.
     *
     * @return object The deserialized entity
     *
     * @throws \LogicException If HydraBundle is not available
     */
    public function deserialize($data, $entity)
    {
        if (!$this->container->has('hydra.serializer')) {
            throw new \LogicException('The HydraBundle is not registered in your application.');
        }

        $serializer = $this->container->get('hydra.serializer');

        if (is_object($entity)) {
            return $serializer->deserializeIntoEntity($data, $entity);
        }

        return $serializer->deserialize($data, $entity, self::FORMAT);
    }

    /**
     * Shortcut to validate an entity
     *
     * This method uses the Validator service. It throws an Exception with
     * a status code of "400 Bad Request" in case of validation errors.
     *
     * @param object $entity The entity to validate.
     *
     * @return false|Error If false is returned, no validation errors have
     *                     been found, otherwise an Error response is
     *                     returned.
     *
     * @throws \LogicException If the validator service is not available
     */
    public function validate($entity)
    {
        if (!$this->container->has('validator')) {
            throw new \LogicException('The validator service is not available.');
        }

        $errors = $this->container->get('validator')->validate($entity);

        if (count($errors) === 0) {
            return false;
        }

        // TODO Use Hydra Error instead
        return new JsonLdResponse('{ "error": "Validation error" }', 400);
    }
}
