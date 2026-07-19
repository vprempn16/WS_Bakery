<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

/**
 * Excludes soft-deleted rows (deleted = 1) for tables that define a deleted column.
 */
class NotDeletedScope implements Scope
{
	public function apply(Builder $builder, Model $model): void
	{
		$table = $model->getTable();

		if (!Schema::hasColumn($table, 'deleted')) {
			return;
		}

		$builder->where($table . '.deleted', 0);
	}
}
