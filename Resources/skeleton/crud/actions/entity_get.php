
    /**
     * Retrieves a {{ entity }}
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_retrieve")
     * @Method("GET")
{% endif %}
     *
     * @Hydra\Operation(
     *   status_codes = {
     *     "404" = "If the {{ entity }} entity wasn't found."
     * })
     *
     * @return {{ namespace }}\Entity\{{ entity }}
     */
    public function getAction({{ entity }} $entity)
    {
        return $entity;
    }
