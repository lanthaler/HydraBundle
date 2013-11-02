<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace ML\HydraBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\Common\Util\ClassUtils;
use ML\HydraBundle\JsonLdResponse;
use ML\HydraBundle\HydraApi;
use ML\HydraBundle\Entity\Collection;

/**
 * The SerializerListener class makes sure that the data returned by a
 * controller is serialized as JSON-LD.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class SerializerListener
{
    /**
     * @var ML\HydraBundle\HydraApi
     */
    protected $hydra;

    /**
     * @var Symfony\Component\Serializer\SerializerInterface
     */
    protected $serializer;

    /**
     * Constructor
     *
     * @param HydraApi            $hydraApi    The Hydra API.
     * @param SerializerInterface $serializer The serializer.
     */
    public function __construct(HydraApi $hydraApi, SerializerInterface $serializer, $annotationReader)
    {
        $this->hydraApi = $hydraApi;
        $this->serializer = $serializer;

        // FIXME The annotation reader is only necessary for the isHydraOperation() method which should be moved elsewhere
        $this->annotationReader = $annotationReader;
    }

    /**
     * Marks request that whose controller return value should be serialized
     * by the Hydra serializer
     *
     * @param FilterControllerEvent $event A FilterControllerEvent instance
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!is_array($controller = $event->getController())) {
            return;
        }

        $request = $event->getRequest();

        $method = new \ReflectionMethod($controller[0], $controller[1]);

        if ($this->isHydraOperation($method)) {
            $request->attributes->set('__hydra_serialize', true);

            // TODO Add support for serialization groups and other serialization formats
        }
    }

    /**
     * Renders the template and initializes a new response object with the
     * rendered template content.
     *
     * @param GetResponseForControllerResultEvent $event A GetResponseForControllerResultEvent instance
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();
        $result = $event->getControllerResult();

        if (!$request->attributes->get('__hydra_serialize')) {
            return;
        }

        if (is_array($result) || ($result instanceof \ArrayAccess) || ($result instanceof \Traversable)) {
            $result = new Collection($request->getUri(), $result);
        } elseif (null === $result) {
            $event->setResponse(new JsonLdResponse('', 200));
            return;
        } elseif (!is_object($result)) {
            throw new \Exception("A Hydra controller must return either an array or an object, got a(n) " . gettype($result));
        }

        $serialized = $this->serializer->serialize($result, 'jsonld');

        $event->setResponse(new JsonLdResponse($serialized));
    }

    /**
     * Does the specified method represent a Hydra Operation?
     *
     * This information is used to determine whether it's return value
     * should be serialized by the Hydra serializer.
     *
     * Currently annotations are the only way to specify a method to be a
     * Hydra operation.
     *
     * @param \ReflectionMethod $method The controller method.
     *
     * @return boolean True if the method represents a Hydra Operation,
     *                 false otherwise
     */
    private function isHydraOperation(\ReflectionMethod $method)
    {
        $annotation = $this->annotationReader->getMethodAnnotation(
            $method,
            'ML\HydraBundle\Mapping\Operation'
        );

        return null !== $annotation;
    }
}
