<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace ML\HydraBundle\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use ML\HydraBundle\HydraApi;

/**
 * The ResponseListener adds an HTTP Link header pointing to the Hydra
 * ApiDocumentation.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class ResponseListener
{
    /**
     * @var ML\HydraBundle\HydraApi
     */
    protected $hydra;

    /**
     * Constructor
     *
     * @param HydraApi $hydraApi The Hydra API.
     */
    public function __construct(HydraApi $hydraApi)
    {
        $this->hydra = $hydraApi;
    }

    /**
     * Marks request that whose controller return value should be serialized
     * by the Hydra serializer
     *
     * @param FilterControllerEvent $event A FilterControllerEvent instance
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();

        $response->headers->set(
            'Link',
            '<' . $this->hydra->getDocumentationUrl() . '>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"'
        );
    }
}
