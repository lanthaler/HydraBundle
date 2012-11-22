
    /**
     * Creates a new {{ entity }}
     *
{% if 'annotation' == format %}
     * @Route("/", name="{{ route_name_prefix }}_create")
     * @Method("POST")
{% endif %}
     *
     * @Hydra\Operation(expect = "{{ namespace }}\Entity\{{ entity }}")
     *
     * @return {{ namespace }}\Entity\{{ entity }}
     */
    public function collectionPostAction(Request $request)
    {
        $entity = $this->deserialize($request->getContent(), '{{ namespace }}\Entity\{{ entity }}');

        if (false !== ($errors = $this->validate($entity))) {
            return $errors;
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

{% if 'entity_get' in actions %}
        $iri = $this->generateUrl('{{ route_name_prefix }}_retrieve', array('id' => $entity->getId()), true);

        $response = new JsonLdResponse(
            $this->serialize($entity),
            201,
            array('Content-Location' => $iri)
        );
{% else %}
        return new JsonLdResponse('', 201);
{% endif %}
    }
