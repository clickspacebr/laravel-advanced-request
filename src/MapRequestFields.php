<?php

namespace Clickspace\AdvancedRequest;

trait MapRequestFields
{

    public static $mapFields = [];

    protected static function mapArray($data, $path = null, $return = []) {
        foreach ($data as $key => $value) {
            $newPath = $path . ($path ? "." : "").$key;
            if (is_array($value)) {
                $return = self::mapArray($value, $newPath, $return);
            } else {
                $return[$newPath] = $value;
            }
        }
        return $return;
    }

    public static function formatArray($data) {
        if (isset(self::$mapFields)) {
            $return = [];
            foreach (self::mapArray($data) as $key => $value) {
                if (array_key_exists($key, self::$mapFields))
                    $return[self::$mapFields[$key]] = $value;
            }
        }
        return $return;
    }

    public static function mapRequest($request) {
        return self::formatArray($request->all());
    }

    public function getDateFields() {
        return $this->dates;
    }

}