<?php

namespace App\Http\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class QueryFilter
{
    protected $builder;

    protected $request;

    protected $sortable = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function filter($arr)
    {
        foreach ($arr as $key => $value) {
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }

        return $this->builder;
    }

    protected function sort($value)
    {
        $sortAttributes = explode(',', $value);

        foreach ($sortAttributes as $sortAttribute) {
            $sort = 'asc';

            if (strpos($sortAttribute, '-') === 0) {
                $sort = 'desc';
                $sortAttribute = substr($sortAttribute, 1);
            }

            if (!in_array($sortAttribute, $this->sortable, true) && !array_key_exists($sortAttribute, $this->sortable)) {
                continue;
            }

            $columnName = $this->sortable[$sortAttribute] ?? $sortAttribute;

            $this->builder->orderBy($columnName, $sort);
        }
    }

    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        $filters = $this->request->input('filter', []);

        foreach ($filters as $key => $value) {
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }

        if ($this->request->has('sort')) {
            $this->sort($this->request->input('sort'));
        }

        return $builder;
    }
}
