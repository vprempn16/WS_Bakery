<?php

namespace App\Modules\Api\V1\SavedFilter\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.values.name' => ['required', 'string', 'max:255'],
            'data.values.module' => ['required', 'string', 'in:products,ingredients,vendors,users,inventory_transactions,recipes,branches,production_batches,branch_stocks,branch_transfers,branch_daily_reports,User,Vendor,Ingredient,InventoryTransaction,Product,Recipe,Branch,ProductionBatch,BranchStock,BranchTransfer,BranchDailyReport,Billing'],
            'data.values.isPublic' => ['nullable', 'boolean'],
            'data.values.rules' => ['required', 'array'],
            'data.values.rules.logical_operator' => ['nullable', 'string', 'in:AND,OR,and,or'],
            'data.values.rules.conditions' => ['required', 'array', 'min:1'],
            'data.values.rules.conditions.*.field' => ['required', 'string'],
            'data.values.rules.conditions.*.operator' => ['required', 'string', 'in:=,!=,>,<,>=,<=,like,LIKE,in,IN'],
            'data.values.rules.conditions.*.value' => ['required'],
            'data.values.headerDetails' => ['nullable', 'array'],
            'data.values.headerDetails.*.fieldname' => ['required_with:data.values.headerDetails', 'string'],
            'data.values.headerDetails.*.fieldlabel' => ['required_with:data.values.headerDetails', 'string'],
        ];
    }
}
