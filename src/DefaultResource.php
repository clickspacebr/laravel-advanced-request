<?php

namespace Clickspace\AdvancedRequest;

use Illuminate\Http\Resources\Json\Resource as BaseResource;

class DefaultResource extends BaseResource {
    use MapResourceFields, RequestIncludes;
}