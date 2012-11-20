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
        $documentation = $this->get('hydra.documentation_generator')->getDocumentation();

        return $documentation;
    }

    /**
     * Generates the service documentation
     *
     * @Route("/api-documentation/vardump")
     * @Template()
     */
    public function vardumpAction()
    {
        $documentation = $this->get('hydra.documentation_generator')->getDocumentation();

        ini_set('xdebug.var_display_max_depth', '10');
        die(var_dump($documentation));
    }

    /**
     * Generates the service documentation
     *
     * @Route("/vocab", defaults = { "_format" = "jsonld" }, name="hydra_vocab")
     * @Template()
     */
    public function vocabularyAction()
    {
        $hydra = $this->get('hydra.documentation_generator');
        $vocab = $hydra->getVocabulary();

        return new JsonLdResponse($vocab);
    }

    /**
     * Generates the service documentation
     *
     * @Route("/contexts/{type}.jsonld", defaults = { "_format" = "jsonld" }, name="hydra_context")
     */
    public function getContextAction($type)
    {
        $documentation = $this->get('hydra.documentation_generator')->getDocumentation();

        $properties = $documentation['types'][$type]['properties'];
        unset($properties['@id']);

        $context = array();
        $context['vocab'] = $this->generateUrl('hydra_vocab', array(), true) . '#';
        $context['hydra'] = 'http://purl.org/hydra/core#';

        if ($documentation['types'][$type]['iri']) {
            $context[$type] = $documentation['types'][$type]['iri'];
        } else {
            $context[$type] = 'vocab:' . $type;
        }

        $ranges = array();

        foreach ($properties as $property => $def) {
            if ('@id' === $def['type']) {
                $context[$property] = array('@id' => 'vocab:' . $property, '@type' => '@id');
            } else {
                $context[$property] = (false === strpos($def['iri'], ':'))
                    ? 'vocab:' . $def['iri']
                    : $def['iri'];

                // if (isset($documentation['class2type'][$def['type']])) {
                //     $ranges[$documentation['class2type'][$def['type']]] = true;
                // }
            }
        }

        // foreach ($ranges as $type => $def) {
        //     $context[$property] = 'vocab:' . $type;
        // }

        return new JsonLdResponse(array('@context' => $context));
    }
}
