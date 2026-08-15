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
use App\Services\BranchAccess;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchTransferController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('BranchTransfer', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = BranchTransfer::with(['branch', 'creator'])
            ->withCount('items')
            ->where('organization_id', $orgId);

        if (! $user->isFullAdmin()) {
            if (! $user->branch_id) {
                return $this->error('No branch assigned to this user.', null, null, null, 403);
            }
            $query->where('branch_id', $user->branch_id);
        } else {
            $query->when($request->query('branchId'), function ($q, $branchId) use ($user) {
                try {
                    BranchAccess::assertCanAccessBranch($user, (string) $branchId);
                } catch (\RuntimeException $e) {
                    // Leave query unfiltered only if assertion fails — prefer empty via impossible id
                    $q->whereRaw('1 = 0');

                    return;
                }
                $q->where('branch_id', $branchId);
            });
        }

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

                // Transfers originate from warehouse; destination branch access for non-admins
                BranchAccess::assertCanAccessBranch(AuthUser::user(), $branchId);

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

                    if (! $product->isSellable()) {
                        throw new \RuntimeException(
                            "Product \"{$product->name}\" is inactive and cannot be transferred."
                        );
                    }

                    if ((float) $product->current_stock < $quantity) {
                        throw new \RuntimeException(
                            "Insufficient warehouse stock for {$product->name}. Available: {$product->current_stock}, requested: {$quantity}."
                        );
                    }

                    $product->current_stock = (float) $product->current_stock - $quantity;
                    $product->save();

                    $branchStock = BranchStock::where('organization_id', $orgId)
                        ->where('branch_id', $branchId)
                        ->where('product_id', $productId)
                        ->lockForUpdate()
                        ->first();

                    if (!$branchStock) {
                        $branchStock = BranchStock::create([
                            'organization_id' => $orgId,
                            'branch_id' => $branchId,
                            'product_id' => $productId,
                            'current_stock' => 0,
                        ]);
                        $branchStock = BranchStock::where('id', $branchStock->id)->lockForUpdate()->first();
                    }

                    $branchStock->current_stock = (float) $branchStock->current_stock + $quantity;
                    $branchStock->save();

                    $unit = strtolower((string) ($product->unit ?? ($itemData['unit'] ?? '')));
                    $needsPieces = in_array($unit, ['gm', 'ml', 'pcs'], true);

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
            try {
                BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $transfer->branch_id);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }
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
            try {
                BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $transfer->branch_id);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }

            if (strtolower((string) $transfer->status) === 'cancelled') {
                return $this->error('Cancelled transfers cannot be edited.', null, null, null, 400);
            }

            if (isset($values['status']) && strtolower((string) $values['status']) === 'cancelled') {
                return DB::transaction(function () use ($transfer) {
                    $this->reverseTransferStock($transfer);
                    $transfer->status = 'cancelled';
                    $transfer->save();
                    $transfer->load(['branch', 'items.product']);
                    return $this->success(new BranchTransferResource($transfer), 'Transfer cancelled and stock reversed.');
                });
            }

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
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    /**
     * Cancel/reverse posted transfer. Hard delete is not allowed.
     */
    public function destroy($id, Request $request)
    {
        try {
            return DB::transaction(function () use ($id) {
                /** @var BranchTransfer $transfer */
                $transfer = RecordObject::make('BranchTransfer', $id, [], 'EditView');
                try {
                    BranchAccess::assertCanAccessBranch(AuthUser::user(), (string) $transfer->branch_id);
                } catch (\RuntimeException $e) {
                    return $this->error($e->getMessage(), null, null, null, 403);
                }
                $transfer->load('items');

                if (strtolower((string) $transfer->status) === 'cancelled') {
                    return $this->error('Transfer is already cancelled.', null, null, null, 400);
                }

                $this->reverseTransferStock($transfer);
                $transfer->status = 'cancelled';
                $transfer->save();

                return $this->success(new BranchTransferResource($transfer->load(['branch', 'items.product'])), 'Transfer cancelled and stock reversed.');
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    private function reverseTransferStock(BranchTransfer $transfer): void
    {
        $orgId = $transfer->organization_id;
        $branchId = $transfer->branch_id;
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            $qty = (float) $item->quantity;

            $branchStock = BranchStock::where('organization_id', $orgId)
                ->where('branch_id', $branchId)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (!$branchStock || (float) $branchStock->current_stock < $qty) {
                throw new \RuntimeException(
                    'Cannot reverse transfer: branch stock is insufficient (already sold).'
                );
            }

            $branchStock->current_stock = (float) $branchStock->current_stock - $qty;
            $branchStock->save();

            $product = Product::where('organization_id', $orgId)
                ->where('id', $item->product_id)
                ->lockForUpdate()
                ->firstOrFail();
            $product->current_stock = (float) $product->current_stock + $qty;
            $product->save();
        }
    }
}
