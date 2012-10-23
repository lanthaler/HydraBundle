
    /**
     * Replaces an existing {{ entity }} entity
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_replace")
     * @Method("PUT")
{% endif %}
     *
     * @Hydra\Operation(
     *   expect = "{{ namespace }}\Entity\{{ entity }}",
     *   status_codes = {
     *     "404" = "If the {{ entity }} entity wasn't found."
     *   }
     * )
     *
     * @return {{ namespace }}\Entity\{{ entity }}
     */
    public function putAction(Request $request, {{ entity }} $entity)
    {
        $em = $this->getDoctrine()->getManager();

        $editForm = $this->createForm(new {{ entity_class }}Type(), $entity);
        $editForm->bind($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('{{ route_name_prefix }}_retrieve', array('id' => $id)));
        }

        return $entity;
    }
