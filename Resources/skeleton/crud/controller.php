<?php

namespace {{ namespace }}\Controller{{ entity_namespace ? '\\' ~ entity_namespace : '' }};

{% if 'collection_post' in actions or 'entity_put' in actions or 'entity_delete' in actions %}
use Symfony\Component\HttpFoundation\Request;
{%- endif %}

use ML\HydraBundle\Controller\HydraController;
use ML\HydraBundle\Mapping as Hydra;
use ML\HydraBundle\JsonLdResponse;
{% if 'annotation' == format -%}
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
{%- endif %}

use {{ namespace }}\Entity\{{ entity }};

/**
 * {{ entity }} controller
 *
{% if 'annotation' == format %}
 * @Route("/{{ route_prefix }}")
{% endif %}
 */
class {{ entity_class }}Controller extends HydraController
{

    {%- if 'collection_get' in actions %}
        {%- include 'actions/collection_get.php' %}
    {%- endif %}

    {%- if 'collection_post' in actions %}
        {%- include 'actions/collection_post.php' %}
    {%- endif %}

    {%- if 'entity_get' in actions %}
        {%- include 'actions/entity_get.php' %}
    {%- endif %}

    {%- if 'entity_put' in actions %}
        {%- include 'actions/entity_put.php' %}
    {%- endif %}

    {%- if 'entity_delete' in actions %}
        {%- include 'actions/entity_delete.php' %}
    {%- endif %}
}
