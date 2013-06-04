
    /**
     * Deletes a {{ entity }}
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_delete")
     * @Method("DELETE")
{% endif %}
     *
     * @return void
     */
    public function deleteAction(Request $request, {{ entity }} $entity)
    {
        $em = $this->getDoctrine()->getManager();

        $em->remove($entity);
        $em->flush();
    }
