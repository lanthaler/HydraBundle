
    /**
     * Deletes a {{ entity }} entity
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_delete")
     * @Method("DELETE")
{% endif %}
     *
     * @Hydra\Operation(
     *   status_codes = {
     *     "404" = "If the {{ entity }} entity wasn't found."
     * })
     *
     * @return void
     */
    public function deleteAction(Request $request, {{ entity }} $entity)
    {
        $em = $this->getDoctrine()->getManager();

        $em->remove($entity);
        $em->flush();
    }
