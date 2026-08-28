<?php

namespace App\Modules\Api\V1\BranchTransfer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Branch\Models\Branch;
use App\Modules\Api\V1\Organization\Models\Organization;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer;
use App\Modules\Api\V1\BranchTransfer\Models\BranchTransferItem;
use App\Modules\Api\V1\BranchTransfer\Requests\StoreBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Requests\UpdateBranchTransferRequest;
use App\Modules\Api\V1\BranchTransfer\Resources\BranchTransferResource;
use App\Modules\Api\V1\BranchTransfer\Services\BranchTransferStockService;
use App\Modules\Api\V1\Product\Models\Product;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\BranchAccess;
use App\Services\CRM\RecordObject;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BranchTransferController extends Controller
{
    public function __construct(
        private readonly BranchTransferStockService $stockService
    ) {}

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

        try {
            BranchAccess::applyTransferListBranchScope($query, $request, $user);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
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

        if ($request->filled('dateFrom')) {
            $query->whereDate('transfer_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('transfer_date', '<=', $request->query('dateTo'));
        }

        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('BranchTransfer', 'DetailView');

        return $this->paginated(BranchTransferResource::collection($transfers)->resource, $fieldList);
    }

    public function store(StoreBranchTransferRequest $request)
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return $this->error('Idempotency-Key header is required for branch transfers.', null, null, null, 422);
        }

        $cacheKey = 'idempotency:branch-transfer:create:' . AuthUser::id() . ':' . hash('sha256', $idempotencyKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['body'], $cached['status'])) {
            return response()->json($cached['body'], $cached['status']);
        }

        $lock = Cache::lock($cacheKey . ':lock', 30);
        if (! $lock->get()) {
            for ($i = 0; $i < 20; $i++) {
                usleep(100000);
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && isset($cached['body'], $cached['status'])) {
                    return response()->json($cached['body'], $cached['status']);
                }
            }

            return $this->error('A matching transfer request is already being processed. Please wait.', null, null, null, 409);
        }

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['body'], $cached['status'])) {
                return response()->json($cached['body'], $cached['status']);
            }

            $values = $request->input('data.values');
            $itemsData = $request->input('data.relatedRecords.items', []);
            $orgId = AuthUser::organizationId();

            $response = DB::transaction(function () use ($values, $itemsData, $orgId) {
                $branchId = $values['branchId'];

                try {
                    RecordObject::make('Branch', $branchId, [], 'DetailView');
                } catch (\Exception $e) {
                    throw new \RuntimeException('The selected branch does not exist or access is denied.');
                }

                $destination = Branch::where('organization_id', $orgId)->where('id', $branchId)->first();
                if (! $destination) {
                    throw new \RuntimeException('The selected branch does not exist or access is denied.');
                }
                if (strtolower((string) ($destination->type ?? '')) === 'warehouse') {
                    throw new \RuntimeException('Transfers must target a retail branch, not the warehouse.');
                }

                // IMPORTANT: use transfer-destination rules — NOT assertCanAccessBranch().
                // Warehouse staff are assigned to the warehouse, but destinations are retail.
                BranchAccess::assertCanAccessTransferDestination(AuthUser::user(), $branchId);

                foreach ($itemsData as $itemData) {
                    try {
                        RecordObject::make('Product', $itemData['productId'], [], 'DetailView');
                    } catch (\Exception $e) {
                        throw new \RuntimeException('A selected product does not exist or access is denied.');
                    }
                }

                $this->stockService->assertWarehouseAvailability($orgId, $itemsData);

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
                $transfer->status = BranchTransferStockService::STATUS_PENDING;
                $transfer->save();

                foreach ($itemsData as $itemData) {
                    $productId = $itemData['productId'];
                    $quantity = (float) $itemData['quantity'];

                    $product = Product::where('organization_id', $orgId)
                        ->where('id', $productId)
                        ->firstOrFail();

                    $piecesRaw = $itemData['pieces'] ?? null;
                    $pieces = null;
                    if ($piecesRaw !== null && $piecesRaw !== '') {
                        $pieces = (float) $piecesRaw;
                    }

                    $item = new BranchTransferItem();
                    $item->organization_id = $orgId;
                    $item->branch_transfer_id = $transfer->id;
                    $item->product_id = $productId;
                    $item->quantity = $quantity;
                    // Always bind unit from product — never trust client unit for ledger rows.
                    $item->unit = $product->unit;
                    $item->pieces = $pieces;
                    $item->save();
                }

                $transfer->load(['branch', 'items.product']);

                return $this->success(
                    new BranchTransferResource($transfer),
                    'Transfer created as pending. Dispatch to deduct warehouse stock, then receive to credit the branch.',
                    201
                );
            });

            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getData(true),
            ], now()->addHours(24));

            return $response;
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to log transfer: ' . $e->getMessage(), null, null, null, 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function show($id, Request $request)
    {
        try {
            /** @var BranchTransfer $transfer */
            $transfer = RecordObject::make('BranchTransfer', $id, [], 'DetailView');
            try {
                BranchAccess::assertCanAccessTransferDestination(AuthUser::user(), (string) $transfer->branch_id);
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
            $values = $request->safeValues();
            $itemsData = $request->safeItems();
            $orgId = AuthUser::organizationId();
            $user = AuthUser::user();

            /** @var BranchTransfer $transfer */
            $transfer = BranchTransfer::where('organization_id', $orgId)->findOrFail($id);
            $permissionService = new \App\Services\PermissionService($user);
            $currentStatus = $this->stockService->normalizeStatus($transfer->status);
            $toStatus = isset($values['status'])
                ? $this->stockService->normalizeStatus($values['status'])
                : null;
            $hasItemsPayload = $itemsData !== null;
            $hasFieldEdits = isset($values['transferDate']) || array_key_exists('notes', $values);
            $isReceiveOnly = $toStatus === BranchTransferStockService::STATUS_RECEIVED
                && count($values) === 1
                && ! $hasItemsPayload;

            if ($isReceiveOnly) {
                // Destination branch staff receive incoming stock. This is a workflow
                // action, not permission to edit transfer dates/notes or dispatch.
                if (! $permissionService->hasPermission('BranchTransfer', 'view')) {
                    return $this->error("You don't have permission to view BranchTransfer.", null, null, null, 403);
                }
                BranchAccess::assertCanAccessBranch($user, (string) $transfer->branch_id);
            } else {
                if (! $permissionService->hasPermission('BranchTransfer', 'edit')) {
                    return $this->error("You don't have permission to edit BranchTransfer.", null, null, null, 403);
                }
                BranchAccess::assertCanAccessTransferDestination($user, (string) $transfer->branch_id);

                if (
                    $toStatus === BranchTransferStockService::STATUS_DISPATCHED
                    && ! $user->isFullAdmin()
                    && ! BranchAccess::isWarehouseUser($user)
                ) {
                    return $this->error('Only warehouse staff may dispatch transfers.', null, null, null, 403);
                }
            }

            if ($currentStatus === BranchTransferStockService::STATUS_CANCELLED) {
                throw new \RuntimeException('Cancelled transfers cannot be edited.');
            }

            // Status transitions are exclusive — do not mix with item/field edits.
            if ($toStatus !== null && ($hasItemsPayload || $hasFieldEdits)) {
                throw new \RuntimeException('Status changes cannot be combined with field or item edits.');
            }

            if ($toStatus !== null) {
                return DB::transaction(function () use ($id, $orgId, $toStatus, $user, $isReceiveOnly) {
                    /** @var BranchTransfer $locked */
                    $locked = BranchTransfer::where('organization_id', $orgId)
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    if ($isReceiveOnly) {
                        BranchAccess::assertCanAccessBranch($user, (string) $locked->branch_id);
                    } else {
                        BranchAccess::assertCanAccessTransferDestination($user, (string) $locked->branch_id);
                    }
                    $locked->load('items');

                    $message = $this->stockService->transition($locked, $toStatus);
                    $locked->load(['branch', 'items.product']);

                    return $this->success(new BranchTransferResource($locked), $message);
                });
            }

            // Header/item edits are only allowed while pending (before stock moves).
            if ($hasFieldEdits || $hasItemsPayload) {
                if ($currentStatus !== BranchTransferStockService::STATUS_PENDING) {
                    throw new \RuntimeException('Only pending transfers can be edited. Dispatch or receive to move stock.');
                }

                return DB::transaction(function () use ($id, $orgId, $values, $itemsData, $hasItemsPayload) {
                    /** @var BranchTransfer $locked */
                    $locked = BranchTransfer::where('organization_id', $orgId)
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($this->stockService->normalizeStatus($locked->status) !== BranchTransferStockService::STATUS_PENDING) {
                        throw new \RuntimeException('Only pending transfers can be edited. Dispatch or receive to move stock.');
                    }

                    if (isset($values['transferDate'])) {
                        $locked->transfer_date = $values['transferDate'];
                    }
                    if (array_key_exists('notes', $values)) {
                        $locked->notes = $values['notes'];
                    }
                    $locked->save();

                    if ($hasItemsPayload) {
                        $this->syncPendingItems($locked, $itemsData ?? []);
                    }

                    $locked->load(['branch', 'items.product']);

                    return $this->success(
                        new BranchTransferResource($locked),
                        $hasItemsPayload
                            ? 'Transfer items updated. Stock is unchanged until dispatch.'
                            : 'Transfer log updated successfully.'
                    );
                });
            }

            $transfer->load(['branch', 'items.product']);

            return $this->success(new BranchTransferResource($transfer), 'Transfer log updated successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'not allowed')
                || str_contains(strtolower($message), 'permission')
                ? 403
                : 400;

            return $this->error($message, null, null, null, $status);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    /**
     * Replace transfer line items while pending. Does not mutate warehouse or branch stock.
     *
     * @param  array<int, array{productId:string, quantity:float|int|string, unit?:string|null, pieces?:float|int|string|null}>  $itemsData
     */
    private function syncPendingItems(BranchTransfer $transfer, array $itemsData): void
    {
        $orgId = (string) $transfer->organization_id;

        foreach ($itemsData as $itemData) {
            try {
                RecordObject::make('Product', $itemData['productId'], [], 'DetailView');
            } catch (\Exception $e) {
                throw new \RuntimeException('A selected product does not exist or access is denied.');
            }
        }

        $this->stockService->assertWarehouseAvailability($orgId, $itemsData);

        BranchTransferItem::where('organization_id', $orgId)
            ->where('branch_transfer_id', $transfer->id)
            ->delete();

        foreach ($itemsData as $itemData) {
            $productId = $itemData['productId'];
            $quantity = (float) $itemData['quantity'];

            $product = Product::where('organization_id', $orgId)
                ->where('id', $productId)
                ->firstOrFail();

            $piecesRaw = $itemData['pieces'] ?? null;
            $pieces = null;
            if ($piecesRaw !== null && $piecesRaw !== '') {
                $pieces = (float) $piecesRaw;
            }

            $item = new BranchTransferItem();
            $item->organization_id = $orgId;
            $item->branch_transfer_id = $transfer->id;
            $item->product_id = $productId;
            $item->quantity = $quantity;
            $item->unit = $product->unit;
            $item->pieces = $pieces;
            $item->save();
        }
    }

    /**
     * Cancel/reverse posted transfer. Hard delete is not allowed.
     */
    public function destroy($id, Request $request)
    {
        try {
            $orgId = AuthUser::organizationId();

            return DB::transaction(function () use ($id, $orgId) {
                // Permission check first, then row-lock for concurrency-safe cancel.
                RecordObject::make('BranchTransfer', $id, [], 'EditView');
                /** @var BranchTransfer $transfer */
                $transfer = BranchTransfer::where('organization_id', $orgId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->firstOrFail();

                BranchAccess::assertCanAccessTransferDestination(AuthUser::user(), (string) $transfer->branch_id);
                $transfer->load('items');

                $this->stockService->cancel($transfer);

                return $this->success(
                    new BranchTransferResource($transfer->load(['branch', 'items.product'])),
                    'Transfer cancelled and stock reversed.'
                );
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer log not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function invoice($id, Request $request)
    {
        try {
            /** @var BranchTransfer $transfer */
            $transfer = RecordObject::make('BranchTransfer', $id, [], 'DetailView');
            try {
                BranchAccess::assertCanAccessTransferDestination(AuthUser::user(), (string) $transfer->branch_id);
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage(), null, null, null, 403);
            }

            $transfer->load(['branch', 'items.product', 'createdBy']);

            $orgId = $transfer->organization_id;
            $org = Organization::find($orgId);

            // Source Branch (Warehouse / Central Kitchen)
            $sourceBranch = Branch::where('organization_id', $orgId)
                ->where('type', 'warehouse')
                ->first();

            $creatorName = $transfer->createdBy
                ? trim($transfer->createdBy->first_name . ' ' . $transfer->createdBy->last_name)
                : 'Bakery Admin';

            $itemsFormatted = $transfer->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'productNumber' => $item->product->product_number ?? '',
                    'productName' => $item->product->name ?? 'Unknown Product',
                    'category' => ucfirst($item->product->category ?? 'General'),
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit ?? $item->product->unit ?? 'pcs',
                    'pieces' => $item->pieces !== null ? (float) $item->pieces : null,
                ];
            });

            $data = [
                'id' => $transfer->id,
                'transferNumber' => $transfer->transfer_number,
                'transferDate' => $transfer->transfer_date,
                'status' => ucfirst($transfer->status),
                'notes' => $transfer->notes,
                'createdAt' => $transfer->created_at?->toIso8601String(),
                'organization' => [
                    'name' => $org?->name ?? 'Grand Bakery WMS',
                    'email' => $org?->email ?? 'contact@grandbakery.com',
                    'phone' => $org?->phone ?? '+919876543210',
                    'address' => $org?->address ?? '123 Main Bazaar Road, Bangalore, Karnataka',
                ],
                'fromBranch' => [
                    'id' => $sourceBranch?->id ?? null,
                    'name' => $sourceBranch?->name ?? 'Central Kitchen & Warehouse',
                    'type' => 'warehouse',
                    'address' => $sourceBranch?->address ?? 'Plot 45 Industrial Area, Bangalore',
                    'phone' => $sourceBranch?->phone ?? '+919876543211',
                ],
                'toBranch' => [
                    'id' => $transfer->branch?->id ?? null,
                    'name' => $transfer->branch?->name ?? 'Retail Outlet',
                    'type' => $transfer->branch?->type ?? 'retail',
                    'address' => $transfer->branch?->address ?? '12 Commercial Street, Bangalore',
                    'phone' => $transfer->branch?->phone ?? '+919876543212',
                ],
                'issuedBy' => $creatorName,
                'items' => $itemsFormatted,
                'totalItems' => count($itemsFormatted),
                'totalQuantity' => (float) $itemsFormatted->sum('quantity'),
            ];

            return $this->success($data, 'Invoice generated successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Transfer record not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $status = str_contains(strtolower($message), 'permission') || str_contains(strtolower($message), 'not allowed')
                ? 403
                : 500;
            $prefix = $status === 403 ? '' : 'Failed to generate invoice: ';

            return $this->error($prefix . $message, null, null, null, $status);
        }
    }
}
