
    /**
     * Retrieves a {{ entity }}
     *
{% if 'annotation' == format %}
     * @Route("/{id}", name="{{ route_name_prefix }}_retrieve")
     * @Method("GET")
{% endif %}
     *
     * @return {{ namespace }}\Entity\{{ entity }}
     */
    public function getAction({{ entity }} $entity)
    {
        return $entity;
    }
