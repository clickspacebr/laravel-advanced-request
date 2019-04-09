<?php

namespace Clickspace\AdvancedRequest;

trait ControllerTrait
{
    protected $model = null;
    protected $resource = DefaultResource::class;
    protected $countResource = null;

    protected $uuids = [];
    protected $allowableFilters = [];
    protected $allowableRelationships = [];

    protected $defaults = [
        'includes' => [],
        'sort' => [],
        'limit' => 50,
        'page' => 1,
        'mode' => 'embed',
        'filter_groups' => []
    ];

    protected $maxValues = [
        'limit' => 50
    ];

}