<?php

namespace Clickspace\AdvancedRequest;

use Spatie\BinaryUuid\HasBinaryUuid;

class BaseModel extends \Illuminate\Database\Eloquent\Model
{
    use HasBinaryUuid, MapRequestFields;

    public $incrementing = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->uuids = self::$uuidAttributes;
    }

    public function getKeyName()
    {
        return 'id';
    }

    protected $uuidSuffix = '_str';
    public $uuids = [];

    public static $allowableRelationships = [];
    public static $allowableFilters = [];
    public static $defaultIncludes = [];
    public static $uuidAttributes = [];

    public static function filterByAccess($query, $args = [])
    {
    }
}