<?php

namespace Clickspace\AdvancedRequest;

trait ControllerMethods
{

    public function index() {
        $resourceOptions = $this->parseResourceOptions(app('request'));
        $query = $this->model::query();
        $this->model::filterByAccess($query);
        $this->applyResourceOptions($query, $resourceOptions);
        $results = $query->paginate($resourceOptions['limit'])->appends($resourceOptions);
        return $this->resource::collection($results)->response(collect($resourceOptions));
    }

    public function show($id) {
        $resourceOptions = $this->parseResourceOptions(app('request'));
        $query = $this->model::withUuid($id);
        $this->model::filterByAccess($query);
        $this->applyResourceOptions($query, $resourceOptions);
        $results = $query->firstOrFail();
        return new $this->resource($results);
    }

}