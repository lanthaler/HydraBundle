
    /**
     * Creates a new {{ entity }} entity
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
        $entity  = new {{ entity_class }}();
        $form = $this->createForm(new {{ entity_class }}Type(), $entity);
        $form->bind($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            {% if 'entity_get' in actions -%}
                return $this->redirect($this->generateUrl(
                '{{ route_name_prefix }}_retrieve',
                 array('id' => $entity->getId())
            ));
            {%- else -%}
                return $this->redirect($this->generateUrl('{{ route_name_prefix }}_collection_get'));
            {%- endif %}

        }

{% if 'annotation' == format %}
        return array(
            'entity' => $entity,
            'form'   => $form->createView(),
        );
{% else %}
        return $this->render('{{ bundle }}:{{ entity|replace({'\\': '/'}) }}:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
{% endif %}
    }
