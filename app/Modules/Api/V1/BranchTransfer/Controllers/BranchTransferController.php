<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\BranchTransfer\Models\BranchStock;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem;
use App\Modules\Api\V1\BranchTransfer\Requests\StoreBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Requests\UpdateBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchTransferResource;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchTransferController extends Controller
{
    public function index(Request $request)
    {
        $orgId = AuthUser::organizationId();
        $perPage = $request->query('per_page', 20);

        $query = BranchTransfer::with(['branch'])
            ->withCount('items')
            ->where('organization_id', $orgId);

        $query->when($request->query('branchId'), function ($q, $branchId) {
            $q->where('branch_id', $branchId);
        });

        $query->when($request->query('search'), function ($q, $search) {
            $q->where('transfer_number', 'like', "%{$search}%");
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'branch_transfers', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'branch_transfers', $rules);
            }
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchTransfer', 'DetailView');

        return $this->paginated(BranchTransferResource::collection($transfers)->resource, $fieldList);
    }

    public function store(StoreBranchTransferRequest $request)
    {
        $values = $request->input('data.values');
        $itemsData = $request->input('data.relatedRecords.items', []);
        $orgId = AuthUser::organizationId();

        try {
            return DB::transaction(function () use ($values, $itemsData, $orgId) {
                $branchId = $values['branchId'];

                try {
                    RecordObject::make('Branch', $branchId, [], 'DetailView');
                } catch (\Exception $e) {
                    return $this->error('The selected branch does not exist or access is denied.');
                }

                foreach ($itemsData as $itemData) {
                    try {
                        RecordObject::make('Product', $itemData['productId'], [], 'DetailView');
                    } catch (\Exception $e) {
                        return $this->error('A selected product does not exist or access is denied.');
                    }
                }

                /** @var BranchTransfer $transfer */
                $transfer = RecordObject::make('BranchTransfer', null, [
                    'branchId' => $branchId,
                    'transferDate' => $values['transferDate'],
                    'notes' => $values['notes'] ?? null,
                ], 'CreateView');
                $transfer->organization_id = $orgId;
                $transfer->branch_id = $branchId;
                $transfer->transfer_date = $values['transferDate'];
                $transfer->notes = $values['notes'] ?? null;
                $transfer->created_by = AuthUser::id();
                $transfer->status = 'completed';
                $transfer->save();

                foreach ($itemsData as $itemData) {
                    $productId = $itemData['productId'];
                    $quantity = (float) $itemData['quantity'];

                    $product = Product::where('organization_id', $orgId)
                        ->where('id', $productId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((float) $product->current_stock < $quantity) {
                        throw new \RuntimeException(
                            "Insufficient warehouse stock for {$product->name}. Available: {$product->current_stock}, requested: {$quantity}."
                        );
                    }

                    $product->current_stock = (float) $product->current_stock - $quantity;
                    $product->save();

                    $branchStock = BranchStock::firstOrCreate(
                        [
                            'organization_id' => $orgId,
                            'branch_id' => $branchId,
                            'product_id' => $productId,
                        ],
                        ['current_stock' => 0]
                    );
                    $branchStock->current_stock = (float) $branchStock->current_stock + $quantity;
                    $branchStock->save();

                    $category = strtolower((string) ($product->category ?? ''));
                    $needsPieces = $category !== 'spices';

                    $item = new BranchTransferItem();
                    $item->organization_id = $orgId;
                    $item->branch_transfer_id = $transfer->id;
                    $item->product_id = $productId;
                    $item->quantity = $quantity;
                    $item->unit = $itemData['unit'] ?? $product->unit;
                    $item->pieces = $needsPieces ? ($itemData['pieces'] ?? null) : null;
                    $item->save();
                }

                $transfer->load(['branch', 'items.product']);

                return $this->success(
                    new BranchTransferResource($transfer),
                    'Transfer logged successfully.',
                    201
                );
            });
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to log transfer: ' . $e->getMessage(), null, null, null, 500);
        }
    }

    public function show($id, Request $request)
    {
        try {
            /** @var BranchTransfer $transfer */
            $transfer = RecordObject::make('BranchTransfer', $id, [], 'DetailView');
            $transfer->load(['branch', 'items.product']);
            $resource = new BranchTransferResource($transfer);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchTransfer', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(UpdateBranchTransferRequest $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            /** @var BranchTransfer $transfer */
            $transfer = RecordObject::make('BranchTransfer', $id, $values, 'EditView');
            if (isset($values['transferDate'])) {
                $transfer->transfer_date = $values['transferDate'];
            }
            if (array_key_exists('notes', $values)) {
                $transfer->notes = $values['notes'];
            }
            $transfer->save();
            $transfer->load(['branch', 'items.product']);

            return $this->success(new BranchTransferResource($transfer), 'Transfer log updated successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            /** @var BranchTransfer $transfer */
            $transfer = RecordObject::make('BranchTransfer', $id, [], 'EditView');
            $transfer->deleteRecord();

            return $this->success(null, 'Transfer log successfully deleted.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }
}
