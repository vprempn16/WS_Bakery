<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\BranchTransfer\Requests\StoreBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Requests\UpdateBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchTransferResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchTransferController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;
        $perPage = $request->query('per_page', 20);

        $query = BranchTransfer::with(['branch', 'product'])
            ->where('organization_id', $orgId);

        // Filters
        $query->when($request->query('branchId'), function ($q, $branchId) {
            $q->where('branch_id', $branchId);
        });

        $query->when($request->query('productId'), function ($q, $productId) {
            $q->where('product_id', $productId);
        });

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('transfer_number', 'like', "%{$search}%");
        });

        // Apply saved filter if provided
        if ($request->has('savedFilterId')) {
            $savedFilter = \App\Modules\Api\V1\SavedFilter\Models\SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_transfers', $savedFilter->rules);
        }

        // Apply dynamic query rules if provided
        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                \App\Modules\Api\V1\SavedFilter\Services\QueryFilterService::apply($query, 'branch_transfers', $rules);
            }
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('BranchTransfer');

        return $this->paginated(BranchTransferResource::collection($transfers)->resource, $fieldList);
    }

    public function store(StoreBranchTransferRequest $request)
    {
        $values = $request->input('data.values');
        $orgId = $request->user()->organization_id;

        try {
            DB::beginTransaction();

            $quantity = $values['quantity'];
            $productId = $values['productId'];
            $branchId = $values['branchId'];

            // Deduct from Warehouse Stock
            $product = Product::where('organization_id', $orgId)->findOrFail($productId);
            
            if ($product->current_stock < $quantity) {
                return $this->error('Insufficient warehouse stock for transfer.', null, null, null, 400);
            }

            $product->current_stock -= $quantity;
            $product->save();

            // Add to Branch Stock
            $branchStock = BranchStock::firstOrCreate(
                ['organization_id' => $orgId, 'branch_id' => $branchId, 'product_id' => $productId],
                ['current_stock' => 0]
            );
            $branchStock->current_stock += $quantity;
            $branchStock->save();

            // Create Transfer Log
            $transfer = BranchTransfer::create([
                'organization_id' => $orgId,
                'branch_id' => $branchId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'transfer_date' => $values['transferDate'],
                'notes' => $values['notes'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'completed',
            ]);

            DB::commit();
            return $this->success(new BranchTransferResource($transfer), 'Transfer logged successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to log transfer: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            $orgId = $request->user()->organization_id;
            $transfer = BranchTransfer::with(['branch', 'product'])
                ->where('organization_id', $orgId)
                ->findOrFail($id);

            $resource = new BranchTransferResource($transfer);
            $fieldList = \App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig::getMappedFields('BranchTransfer');
            
            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request())
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        }
    }

    public function update(UpdateBranchTransferRequest $request, $id)
    {
        try {
            $orgId = $request->user()->organization_id;
            $transfer = BranchTransfer::where('organization_id', $orgId)->findOrFail($id);
            $values = $request->input('data.values');

            // Only allow updating non-financial/inventory impacting fields
            $transfer->update([
                'transfer_date' => $values['transferDate'],
                'notes' => $values['notes'] ?? null,
            ]);

            return $this->success(new BranchTransferResource($transfer), 'Transfer log updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $orgId = $request->user()->organization_id;
            $transfer = BranchTransfer::where('organization_id', $orgId)->findOrFail($id);
            
            // Soft operation: Just delete the log without reversing stock movements
            // Full reversal logic omitted for Phase 2 simplicity.
            $transfer->delete();

            return $this->success(null, 'Transfer log successfully deleted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        }
    }
}
