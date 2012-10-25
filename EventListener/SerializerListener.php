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
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Common\Util\ClassUtils;
use ML\HydraBundle\DocumentationGenerator;
use Symfony\Component\Routing\RouterInterface;
use ML\HydraBundle\Serializer\Serializer;
use ML\HydraBundle\Collection\Collection;

/**
 * The SerializerListener class makes sure that the data returned by a
 * controller is serialized as JSON-LD.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class SerializerListener
{
    /**
     * @var Symfony\Component\DependencyInjection\ContainerInterface
     */
    //protected $container;

    /**
     * @var Symfony\Component\Routing\RouterInterface
     */
    //protected $router;

    /**
     * @var ML\HydraBundle\DocumentationGenerator
     */
    protected $hydra;

    /**
     * @var ML\HydraBundle\Serializer\Serializer
     */
    protected $serializer;

    /**
     * @var array
     */
    //protected $hydraDoc;

    /**
     * Constructor.
     *
     * @param DocumentationGenerator $documentationGenerator The Hydra documentation generator
     * @param RouterInterface $router The router
     */
    public function __construct(DocumentationGenerator $documentationGenerator, RouterInterface $router)
    {
        $this->hydra = $documentationGenerator;
        $this->serializer = new Serializer($this->hydra->getDocumentation(), $router);
    }

    /**
     * Guesses the template name to render and its variables and adds them to
     * the request object.
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

        if ($this->hydra->isOperation($method)) {
            $request->attributes->set('__hydra_serialize', true);

            // TODO Add support for serialization groups

            $type = $this->hydra->getType($method);
            if (null !== $type['type']) {
                $request->attributes->set('__hydra_return_type', $type);
            }
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
        } elseif (!is_object($result)) {
            throw new \Exception("A Hydra controller must return either an array or an object, got a(n) " . gettype($result));
        }

        $serialized = $this->serializer->serialize($result, 'jsonld');

if ($request->query->get('debug', false)) {
    print "<pre>" . $serialized . "</pre>";
} else {
    print $serialized;
}

//print "<pre>" . json_encode($serialized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "</pre>";
// var_dump($serialized);
//var_dump($this->hydraDoc['types'][$className]);
die();

        $event->setResponse(new JsonResponse($serialized));
    }
}
