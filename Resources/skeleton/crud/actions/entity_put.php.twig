
    /**
     * Replaces an existing {{ entity }}
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_replace")
     * @Method("PUT")
{% endif %}
     *
     * @Hydra\Operation(
     *   expect = "{{ namespace }}\Entity\{{ entity }}",
     *   status_codes = {
     *     "404" = "If the {{ entity }} wasn't found."
     *   }
     * )
     *
     * @return {{ namespace }}\Entity\{{ entity }}
     */
    public function putAction(Request $request, {{ entity }} $entity)
    {
        $entity = $this->deserialize($request->getContent(), $entity);

        if (false !== ($errors = $this->validate($entity))) {
            return $errors;
        }

        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();

        return $entity;
    }
