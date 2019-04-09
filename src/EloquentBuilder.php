<?php

namespace Clickspace\AdvancedRequest;

use DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait EloquentBuilder
{

    /**
     * Page sort
     * @param array $sort
     * @return array
     */
    protected function parseSort(array $sort)
    {
        return array_map(function ($sort) {
            if (!isset($sort['direction'])) {
                $sort['direction'] = 'asc';
            }
            return $sort;
        }, $sort);
    }

    /**
     * Parse include strings into resource and modes
     * @param  array $includes
     * @return array The parsed resources and their respective modes
     */
    protected function parseIncludes(array $includes)
    {
        $return = [
            'includes' => [],
            'modes' => []
        ];
        foreach ($includes as $include) {
            $explode = explode(':', $include);
            if (!isset($explode[1])) {
                $explode[1] = camel_case($this->defaults['mode']);
            }
            $return['includes'][] = $explode[0];
            $return['modes'][$explode[0]] = $explode[1];
        }

        return $return;
    }

    /**
     * Parse filter group strings into filters
     * Filters are formatted as key:operator(value)
     * Example: name:eq(esben)
     * @param  array $filter_groups
     * @return array
     */
    protected function parseFilterGroups(array $filter_groups)
    {
        $return = [];
        foreach ($filter_groups as $indexGroup => $group) {
            if (!is_array($group) or !array_key_exists('filters', $group)) {
                throw new \InvalidArgumentException('Filter group \'' . $indexGroup . '\' does not have the \'filters\' key.');
            }
            foreach ($group['filters'] as $indexFilter => $filter) {
                if (!is_array($filter))
                    throw new \InvalidArgumentException('Filter \'' . $indexFilter . '\' in group \'' . $indexGroup . '\' is not valid.');
                if (!array_key_exists('key', $filter))
                    throw new \InvalidArgumentException('Filter \'' . $indexFilter . '\' in group \'' . $indexGroup . '\' does not have the \'key\' key.');
                if (!array_key_exists('value', $filter))
                    throw new \InvalidArgumentException('Filter \'' . $indexFilter . '\' in group \'' . $indexGroup . '\' does not have the \'value\' key.');

                if (!in_array($filter['key'], $this->model::$allowableFilters))
                    throw new \InvalidArgumentException('Filter \'' . $indexFilter . '\' with key \'' . $filter['key'] . '\' in group \'' . $indexGroup . '\' is not allowed.');

                if (!isset($filter['not'])) {
                    $group['filters'][$indexFilter]['not'] = false;
                }
            }
            $return[] = [
                'filters' => $group['filters'],
                'or' => isset($group['or']) ? $group['or'] : false
            ];
        }
        return $return;
    }

    /**
     * Parse GET parameters into resource options
     * @return array
     */
    protected function parseResourceOptions($request)
    {
        $this->defaults = array_merge([
            'includes' => [],
            'sort' => [],
            'limit' => null,
            'page' => null,
            'mode' => 'embed',
            'filter_groups' => []
        ], $this->defaults);
        $includes = $this->parseIncludes($request->get('includes', $this->defaults['includes']));
        $sort = $this->parseSort($request->get('sort', $this->defaults['sort']));
        $limit = $request->get('limit', $this->defaults['limit']);
        $page = $request->get('page', $this->defaults['page']);

        $inputFilterGroups = $request->get('filter_groups', $this->defaults['filter_groups']);
        if (!is_array($inputFilterGroups))
            throw new \InvalidArgumentException('Filter group is not valid.');
        $filter_groups = $this->parseFilterGroups($inputFilterGroups);

        if ($page !== null && $limit === null) {
            throw new InvalidArgumentException('Cannot use page option without limit option');
        }

        if ($limit > $this->maxValues['limit'])
            $limit = $this->maxValues['limit'];

        return [
            'includes' => $includes['includes'],
            'modes' => $includes['modes'],
            'sort' => $sort,
            'limit' => (integer)$limit,
            'page' => (integer)$page,
            'filter_groups' => $filter_groups
        ];
    }

    /**
     * Apply resource options to a query builder
     * @param  $queryBuilder
     * @param  array $options
     * @return Builder
     */
    protected function applyResourceOptions($queryBuilder, array $options = [])
    {
        if (empty($options)) {
            return $queryBuilder;
        }

        extract($options);

        if (isset($includes))
            $queryBuilder = $this->applyIncludes($queryBuilder, $includes);

        if (isset($filter_groups)) {
            $filterJoins = $this->applyFilterGroups($queryBuilder, $filter_groups, []);
        }

        if (isset($sort)) {
            if (!is_array($sort)) {
                throw new InvalidArgumentException('Sort should be an array.');
            }

            if (!isset($filterJoins)) {
                $filterJoins = [];
            }

            $sortingJoins = $this->applySorting($queryBuilder, $sort, $filterJoins);
        }

        if (isset($distinct)) {
            $queryBuilder->distinct();
        }

        return $queryBuilder;
    }

    /**
     * @param $queryBuilder
     * @param array $filterGroups
     * @param array $previouslyJoined
     * @return array
     */
    protected function applyIncludes($queryBuilder, array $includes = [])
    {
        if (!is_array($includes)) {
            throw new InvalidArgumentException('Includes should be an array.');
        }

        foreach ($includes as $include)
            if (!in_array($include, $this->allowableRelationships))
                throw new InvalidArgumentException('Includes: \'' . $include . '\' can not be included.');

        $queryBuilder->with($includes);

        return $queryBuilder;
    }

    /**
     * @param $queryBuilder
     * @param array $filterGroups
     * @param array $previouslyJoined
     * @return array
     */
    protected function applyFilterGroups($queryBuilder, array $filterGroups = [], array $previouslyJoined = [])
    {
        $joins = [];
        foreach ($filterGroups as $group) {
            $or = $group['or'];
            $filters = $group['filters'];

            $allowableFilters = $this->allowableFilters;

            $queryBuilder->where(function (Builder $query) use ($filters, $or, &$joins, $allowableFilters) {
                foreach ($filters as $filter) {
                    if (in_array($filter, $allowableFilters))
                        throw new InvalidArgumentException('Filters: \'' . $filter . '\' can not be used.');
                    $this->applyFilter($query, $filter, $or, $joins);
                }
            });
        }

        foreach (array_diff($joins, $previouslyJoined) as $join) {
            $this->joinRelatedModelIfExists($queryBuilder, $join);
        }

        return $joins;
    }

    protected function callFilter(Builder $query, $method, $table, $key, $fieldFormat, $clauseOperator, $value, $relationships) {

        // If we do not assign database field, the customer filter method
        // will fail when we execute it with parameters such as CAST(%s AS TEXT)
        // key needs to be reserved
        if (is_null($fieldFormat))
            $fieldFormat = '%s.%s';


        if (!$relationships) {

            $databaseField = DB::raw(sprintf($fieldFormat, $table, $key));

            if (is_null($clauseOperator)) {
                if (is_null($value)) {
                    call_user_func([$query, $method], $databaseField);
                } else {
                    call_user_func_array([$query, $method], [
                        $databaseField, $value
                    ]);
                }
            } else {
                call_user_func_array([$query, $method], [
                    $databaseField, $clauseOperator, $value
                ]);
            }

        } else {
            foreach ($relationships as $relationship) {
                $key = str_replace($relationship.".", "", $key);
            }

            $query->whereHas($relationships[0], function ($query) use ($method, $table, $key, $fieldFormat, $clauseOperator, $value, $relationships) {
                $relationship = $relationships[0];
                array_splice($relationships, 0, 1);
                $this->callFilter($query, $method, $relationship, $key, $fieldFormat, $clauseOperator, $value, $relationships);

            });

        }

    }

    /**
     * @param $queryBuilder
     * @param array $filter
     * @param bool|false $or
     * @param array $joins
     */
    protected function applyFilter($queryBuilder, array $filter, $or = false, array &$joins)
    {
        extract($filter);

        $dbType = $queryBuilder->getConnection()->getDriverName();

        $table = $queryBuilder->getModel()->getTable();

        if (in_array($key, $this->model::$uuidAttributes)) {
            if (is_array($value)) {
                $model = $this->model;
                $value = array_map(function ($value) use ($model) {
                    return $model::encodeUuid($value);
                }, $value);
            } else {
                $value = $this->model::encodeUuid($value);
            }
        }

        $relationships = null;
        if (strpos($key, ".")) {
            $relationships = explode(".", $key);
            array_splice($relationships, count($relationships)-1);
        }

        if ($value === 'null' || $value === '') {
            $method = $not ? 'WhereNotNull' : 'WhereNull';

            //call_user_func([$queryBuilder, $method], sprintf('%s.%s', $table, $key));
            $this->callFilter($queryBuilder, $method, $table, $key, null, null, null, $relationships);

        } else {
            $method = filter_var($or, FILTER_VALIDATE_BOOLEAN) ? 'orWhere' : 'where';
            $clauseOperator = null;
            $fieldFormat = null;

            if (!isset($operator))
                $operator = 'eq';

            switch($operator) {
                case 'ct':
                case 'sw':
                case 'ew':
                    $valueString = [
                        'ct' => '%'.$value.'%', // contains
                        'ew' => '%'.$value, // ends with
                        'sw' => $value.'%' // starts with
                    ];

                    $castToText = (($dbType === 'postgres') ? 'TEXT' : 'CHAR');
                    //$databaseField = DB::raw(sprintf('CAST(%s.%s AS ' . $castToText . ')', $table, $key));
                    $fieldFormat = 'CAST(%s.%s AS ' . $castToText . ')';
                    $clauseOperator = ($not ? 'NOT':'') . (($dbType === 'postgres') ? 'ILIKE' : 'LIKE');
                    $value = $valueString[$operator];
                    break;
                case 'eq':
                default:
                    $clauseOperator = $not ? '!=' : '=';
                    break;
                case 'gt':
                    $clauseOperator = $not ? '<' : '>';
                    break;
                case 'gteq':
                    $clauseOperator = $not ? '<' : '>=';
                    break;
                case 'lteq':
                    $clauseOperator = $not ? '>' : '<=';
                    break;
                case 'lt':
                    $clauseOperator = $not ? '>' : '<';
                    break;
                case 'in':
                    if ($or === true) {
                        $method = $not === true ? 'orWhereNotIn' : 'orWhereIn';
                    } else {
                        $method = $not === true ? 'whereNotIn' : 'whereIn';
                    }
                    $clauseOperator = false;
                    break;
                case 'bt':
                    if ($or === true) {
                        $method = $not === true ? 'orWhereNotBetween' : 'orWhereBetween';
                    } else {
                        $method = $not === true ? 'whereNotBetween' : 'whereBetween';
                    }
                    $clauseOperator = false;
                    break;
            }

            $customFilterMethod = $this->hasCustomMethod('filter', $key);
            if ($customFilterMethod) {
                call_user_func_array([$this, $customFilterMethod], [
                    $queryBuilder,
                    $method,
                    $clauseOperator,
                    $value,
                    $clauseOperator // @deprecated. Here for backwards compatibility
                ]);

                // column to join.
                // trying to join within a nested where will get the join ignored.
                $joins[] = $key;
            } else {
                // In operations do not have an operator
                if (in_array($operator, ['in', 'bt'])) {
                    // $this->callFilter($queryBuilder, $method, $table, $key, $fieldFormat, $clauseOperator, $value, $relationships);
                    call_user_func_array([$queryBuilder, $method], [
                       $table.".".$key, $value
                    ]);
                } else {
                    $this->callFilter($queryBuilder, $method, $table, $key, $fieldFormat, $clauseOperator, $value, $relationships);
                }
            }
        }
    }

    /**
     * @param $queryBuilder
     * @param array $sorting
     * @param array $previouslyJoined
     * @return array
     */
    protected function applySorting($queryBuilder, array $sorting, array $previouslyJoined = [])
    {
        $joins = [];
        foreach($sorting as $sortRule) {
            if (is_array($sortRule)) {
                $key = $sortRule['key'];
                $direction = mb_strtolower($sortRule['direction']) === 'asc' ? 'ASC' : 'DESC';
            } else {
                $key = $sortRule;
                $direction = 'ASC';
            }

            $customSortMethod = $this->hasCustomMethod('sort', $key);
            if ($customSortMethod) {
                $joins[] = $key;

                call_user_func([$this, $customSortMethod], $queryBuilder, $direction);
            } else {
                $queryBuilder->orderBy($key, $direction);
            }
        }

        foreach(array_diff($joins, $previouslyJoined) as $join) {
            $this->joinRelatedModelIfExists($queryBuilder, $join);
        }

        return $joins;
    }

    /**
     * @param $type
     * @param $key
     * @return bool|string
     */
    private function hasCustomMethod($type, $key)
    {
        $methodName = sprintf('%s%s', $type, Str::studly($key));
        if (method_exists($this, $methodName)) {
            return $methodName;
        }

        return false;
    }

    /**
     * @param $queryBuilder
     * @param $key
     */
    private function joinRelatedModelIfExists($queryBuilder, $key)
    {
        $model = $queryBuilder->getModel();

        // relationship exists, join to make special sort
        if (method_exists($model, $key)) {
            $relation = $model->$key();
            $type = 'inner';

            if ($relation instanceof BelongsTo) {
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $model->getTable().'.'.$relation->getQualifiedForeignKeyName(),
                    '=',
                    $relation->getRelated()->getTable().'.'.$relation->getOwnerKey(),
                    $type
                );
            } elseif ($relation instanceof BelongsToMany) {
                $queryBuilder->join(
                    $relation->getTable(),
                    $relation->getQualifiedParentKeyName(),
                    '=',
                    $relation->getQualifiedForeignKeyName(),
                    $type
                );
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $relation->getRelated()->getTable().'.'.$relation->getRelated()->getKeyName(),
                    '=',
                    $relation->getQualifiedRelatedKeyName(),
                    $type
                );
            } else {
                $queryBuilder->join(
                    $relation->getRelated()->getTable(),
                    $relation->getQualifiedParentKeyName(),
                    '=',
                    $relation->getQualifiedForeignKeyName(),
                    $type
                );
            }

            $table = $model->getTable();
            $queryBuilder->select(sprintf('%s.*', $table));
        }
    }

    protected function filterByAccess($queryBuilder, $args = []) {
        // $queryBuilder->where('account_id', $args['account_id']);
        return $queryBuilder;
    }

    protected function setByAccess(&$model, $args = []) {
        // $model->account_id = $args['account_id'];
        return $model;
    }
}