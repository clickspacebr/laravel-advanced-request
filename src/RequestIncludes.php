<?php

namespace Clickspace\AdvancedRequest;

use Illuminate\Support\Str;

trait RequestIncludes
{

    static public function makeResource($relation, $data, $request =  null) {
        if (is_a($data, 'Illuminate\Database\Eloquent\Collection'))
            return DefaultResource::collection($data);
        return new DefaultResource($data);
    }

}