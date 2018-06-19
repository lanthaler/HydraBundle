<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use ML\HydraBundle\JsonLdResponse;

/**
 * The documentation generator controller
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class DocumentationGeneratorController extends Controller
{
    /**
     * Generates the service documentation
     *
     * @Route("/api-documentation/")
     * @Template()
     */
    public function indexAction()
    {
        $documentation = $this->get('hydra.api')->getDocumentation();

        return $documentation;
    }

    /**
     * Generates the service documentation
     *
     * @Route("/vocab", defaults = { "_format" = "jsonld" }, name="hydra_vocab")
     * @Template()
     */
    public function vocabularyAction()
    {
        $hydra = $this->get('hydra.api');
        $documentation = $hydra->getDocumentation();

        return new JsonLdResponse($documentation);
    }

    /**
     * Generates the service documentation
     *
     * @Route("/contexts/{type}.jsonld", defaults = { "_format" = "jsonld" }, name="hydra_context")
     */
    public function getContextAction($type)
    {
        $context = $this->get('hydra.api')->getContext($type);

        if (null === $context) {
            $this->createNotFoundException();
        }

        return new JsonLdResponse($context);
    }
}
