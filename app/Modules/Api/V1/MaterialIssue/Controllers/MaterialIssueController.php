<?php

namespace App\Modules\Api\V1\MaterialIssue\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\V1\Ingredient\Models\Ingredient;
use App\Modules\Api\V1\InventoryTransaction\Models\InventoryTransaction;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssue;
use App\Modules\Api\V1\MaterialIssue\Models\MaterialIssueItem;
use App\Modules\Api\V1\MaterialIssue\Requests\StoreMaterialIssueRequest;
use App\Modules\Api\V1\MaterialIssue\Requests\UpdateMaterialIssueRequest;
use App\Modules\Api\V1\MaterialIssue\Resources\MaterialIssueResource;
use App\Modules\Api\V1\SavedFilter\Models\SavedFilter;
use App\Modules\Api\V1\SavedFilter\Services\ModuleFieldConfig;
use App\Modules\Api\V1\SavedFilter\Services\QueryFilterService;
use App\Services\AuthUser;
use App\Services\CRM\RecordObject;
use App\Support\Idempotency;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialIssueController extends Controller
{
    public function index(Request $request)
    {
        $user = AuthUser::requireUser();
        $permissionService = new \App\Services\PermissionService($user);
        if ($deny = $permissionService->denyMessage('MaterialIssue', 'view')) {
            return $this->error($deny, null, null, null, 403);
        }

        $orgId = AuthUser::organizationId();
        $perPage = \App\Support\ApiPagination::perPage($request);

        $query = MaterialIssue::with(['creator'])
            ->withCount('items')
            ->where('organization_id', $orgId);

        $query->when($request->query('search'), function ($q, $search) {
            $like = "%{$search}%";
            $q->where(function ($sub) use ($like) {
                $sub->where('issue_number', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('remarks', 'like', $like)
                    ->orWhereHas('items.ingredient', function ($ingQuery) use ($like) {
                        $ingQuery->where('name', 'like', $like)
                                 ->orWhere('category', 'like', $like);
                    });
            });
        });

        if ($request->has('savedFilterId')) {
            $savedFilter = SavedFilter::where('organization_id', $orgId)
                ->findOrFail($request->query('savedFilterId'));
            QueryFilterService::apply($query, 'material_issues', $savedFilter->rules);
        }

        if ($request->has('rules')) {
            $rules = $request->input('rules');
            if (is_string($rules)) {
                $rules = json_decode($rules, true);
            }
            if (is_array($rules)) {
                QueryFilterService::apply($query, 'material_issues', $rules);
            }
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('issue_date', '>=', $request->query('dateFrom'));
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('issue_date', '<=', $request->query('dateTo'));
        }

        $issues = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $fieldList = ModuleFieldConfig::getApiFieldsForView('MaterialIssue', 'DetailView');

        return $this->paginated(MaterialIssueResource::collection($issues)->resource, $fieldList);
    }

    public function store(StoreMaterialIssueRequest $request)
    {
        [$lock, $cacheKey, $early] = Idempotency::begin(
            'material-issue:create',
            $request->header('Idempotency-Key'),
            true
        );
        if ($early) {
            return $early;
        }

        $values = $request->input('data.values');
        $itemsData = $request->input('data.relatedRecords.items', []);
        $orgId = AuthUser::organizationId();
        $userId = AuthUser::id();

        try {
            $response = DB::transaction(function () use ($values, $itemsData, $orgId, $userId) {
                foreach ($itemsData as $itemData) {
                    try {
                        RecordObject::make('Ingredient', $itemData['ingredientId'], [], 'DetailView');
                    } catch (\Exception $e) {
                        throw new \RuntimeException('A selected ingredient does not exist or access is denied.');
                    }
                }

                /** @var MaterialIssue $issue */
                $issue = RecordObject::make('MaterialIssue', null, [
                    'issueDate' => $values['issueDate'],
                    'notes' => $values['notes'] ?? null,
                ], 'CreateView');
                $issue->organization_id = $orgId;
                $issue->issue_date = $values['issueDate'];
                $issue->notes = $values['notes'] ?? null;
                $issue->created_by = $userId;
                $issue->status = 'posted';
                $issue->save();

                foreach ($itemsData as $itemData) {
                    $ingredientId = $itemData['ingredientId'];
                    $quantity = (float) $itemData['quantity'];

                    $ingredient = Ingredient::where('organization_id', $orgId)
                        ->where('id', $ingredientId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ((float) $ingredient->current_stock < $quantity) {
                        throw new \RuntimeException(
                            "Insufficient stock for {$ingredient->name}. Available: {$ingredient->current_stock}, requested: {$quantity}."
                        );
                    }

                    $ingredient->current_stock = (float) $ingredient->current_stock - $quantity;
                    $ingredient->save();

                    InventoryTransaction::create([
                        'organization_id' => $orgId,
                        'ingredient_id' => $ingredient->id,
                        'type' => 'out',
                        'quantity' => $quantity,
                        'reference_note' => "Material Withdrawal: {$issue->issue_number}",
                    ]);

                    $item = new MaterialIssueItem();
                    $item->organization_id = $orgId;
                    $item->material_issue_id = $issue->id;
                    $item->ingredient_id = $ingredientId;
                    $item->quantity = $quantity;
                    $item->unit = $ingredient->unit;
                    $item->save();
                }

                $issue->load(['creator', 'items.ingredient']);

                return $this->success(
                    new MaterialIssueResource($issue),
                    'Material withdrawal posted. Raw material stock reduced.',
                    201
                );
            });

            Idempotency::remember($cacheKey, $response);

            return $response;
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error('Failed to post material withdrawal: ' . $e->getMessage(), null, null, null, 500);
        } finally {
            Idempotency::release($lock);
        }
    }

    public function show($id)
    {
        try {
            /** @var MaterialIssue $issue */
            $issue = RecordObject::make('MaterialIssue', $id, [], 'DetailView');
            $issue->load(['creator', 'items.ingredient']);
            $resource = new MaterialIssueResource($issue);
            $fieldList = ModuleFieldConfig::getApiFieldsForView('MaterialIssue', 'DetailView');

            return $this->success([
                'fields' => $fieldList,
                'values' => $resource->toArray(request()),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->error('Material withdrawal not found.', null, null, null, 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 403);
        }
    }

    public function update(UpdateMaterialIssueRequest $request, $id)
    {
        try {
            $values = $request->input('data.values') ?? [];
            $orgId = AuthUser::organizationId();

            /** @var MaterialIssue $issue */
            $issue = RecordObject::make('MaterialIssue', $id, [], 'EditView');

            if (strtolower((string) $issue->status) === 'cancelled') {
                throw new \RuntimeException('Cancelled material withdrawals cannot be edited.');
            }

            if (isset($values['status']) && strtolower((string) $values['status']) === 'cancelled') {
                return DB::transaction(function () use ($id, $orgId) {
                    /** @var MaterialIssue $locked */
                    $locked = MaterialIssue::where('organization_id', $orgId)
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (strtolower((string) $locked->status) === 'cancelled') {
                        throw new \RuntimeException('Material withdrawal is already cancelled.');
                    }

                    $this->reverseIssueStock($locked, $orgId);
                    $locked->status = 'cancelled';
                    $locked->save();
                    $locked->load(['creator', 'items.ingredient']);

                    return $this->success(new MaterialIssueResource($locked), 'Material withdrawal cancelled and stock restored.');
                });
            }

            if (isset($values['issueDate'])) {
                $issue->issue_date = $values['issueDate'];
            }
            if (array_key_exists('notes', $values)) {
                $issue->notes = $values['notes'];
            }
            $issue->save();
            $issue->load(['creator', 'items.ingredient']);

            return $this->success(new MaterialIssueResource($issue), 'Material withdrawal updated.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Material withdrawal not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    public function destroy($id)
    {
        $orgId = AuthUser::organizationId();

        try {
            return DB::transaction(function () use ($id, $orgId) {
                /** @var MaterialIssue $issue */
                $issue = RecordObject::make('MaterialIssue', $id, [], 'EditView');

                if (strtolower((string) $issue->status) === 'cancelled') {
                    return $this->error('Material withdrawal is already cancelled.', null, null, null, 400);
                }

                $locked = MaterialIssue::where('organization_id', $orgId)
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->reverseIssueStock($locked, $orgId);
                $locked->status = 'cancelled';
                $locked->save();
                $locked->load(['creator', 'items.ingredient']);

                return $this->success(new MaterialIssueResource($locked), 'Material withdrawal cancelled and stock restored.');
            });
        } catch (ModelNotFoundException $e) {
            return $this->error('Material withdrawal not found.', null, null, null, 404);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), null, null, null, 400);
        }
    }

    private function reverseIssueStock(MaterialIssue $issue, string $orgId): void
    {
        $issue->loadMissing('items');

        foreach ($issue->items as $item) {
            $ingredient = Ingredient::where('organization_id', $orgId)
                ->where('id', $item->ingredient_id)
                ->lockForUpdate()
                ->first();

            if (!$ingredient) {
                continue;
            }

            $qty = (float) $item->quantity;
            $ingredient->current_stock = (float) $ingredient->current_stock + $qty;
            $ingredient->save();

            InventoryTransaction::create([
                'organization_id' => $orgId,
                'ingredient_id' => $ingredient->id,
                'type' => 'in',
                'quantity' => $qty,
                'reference_note' => "Reversed Material Withdrawal: {$issue->issue_number}",
            ]);
        }
    }
}
