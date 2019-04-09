<?php

namespace Clickspace\AdvancedRequest;

use Carbon\Carbon;

trait MapResourceFields
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $request = $request->toArray();

        $response = is_array($this->resource)
            ? $this->resource
            : $this->resource->toArray();

        foreach ($this->resource->getDateFields() as $field) {
            if (isset($response[$field]))
                $response[$field] = Carbon::parse($response[$field])->toIso8601String();
        };

        $code = "";
        if (isset($this->resource::$mapFields)) {
            foreach ($this->resource::$mapFields as $fieldResource => $field) {
                if (!isset($response[$field]))
                    continue;
                $code .= '$response';
                foreach (explode(".", $fieldResource) as $path) {
                    $code .= "[\"$path\"]";
                }
                $code .= " = \"{$response[$field]}\";";
                unset($response[$field]);
            }
            eval($code);
        }

        return $response;

    }

}