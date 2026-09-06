<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\Api\V1\AuditLog\Models\AuditLog;
use App\Services\BranchAccess;
use Illuminate\Support\Str;

class GlobalAuditLogController extends Controller
{
    private array $moduleToModelMap = [
        'User' => 'User',
        'Vendor' => 'Vendor',
        'Ingredient' => 'Ingredient',
        'MaterialIssue' => 'MaterialIssue',
        'MaterialIssueItem' => 'MaterialIssueItem',
        'InventoryTransaction' => 'InventoryTransaction',
        'Product' => 'Product',
        'ProductStockTransaction' => 'ProductStockTransaction',
        'Recipe' => 'Recipe',
        'Branch' => 'Branch',
        'ProductionBatch' => 'ProductionBatch',
        'ProductionPlan' => 'ProductionPlan',
        'ProductionPlanItem' => 'ProductionPlanItem',
        'BranchStock' => 'BranchStock',
        'BranchTransfer' => 'BranchTransfer',
        'BranchTransferItem' => 'BranchTransferItem',
        'BranchDailyReport' => 'BranchDailyReport',
        'BranchDailyReportItem' => 'BranchDailyReportItem',
        'Billing' => 'Billing',
        'BillingItem' => 'BillingItem',
        'SalesReturn' => 'SalesReturn',
        'SalesReturnItem' => 'SalesReturnItem',
    ];

    private array $modelClassMap = [
        'BranchStock' => \App\Modules\Api\V1\BranchTransfer\Models\BranchStock::class,
        'BranchTransfer' => \App\Modules\Api\V1\BranchTransfer\Models\BranchTransfer::class,
        'BranchDailyReport' => \App\Modules\Api\V1\BranchSales\Models\BranchDailyReport::class,
        'Billing' => \App\Modules\Api\V1\Billing\Models\Billing::class,
        'SalesReturn' => \App\Modules\Api\V1\SalesReturn\Models\SalesReturn::class,
        'User' => \App\Modules\Api\V1\User\Models\User::class,
    ];

    private static function eventTypeForUi(string $event): string
    {
        return match (strtolower($event)) {
            'created', 'create', 'insert' => 'create',
            'updated', 'update' => 'update',
            'deleted', 'delete' => 'delete',
            default => strtolower($event),
        };
    }

    /**
     * Build FE-friendly change rows from old/new value maps.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @return list<array{field_name: string, field_label: string, old_value: mixed, new_value: mixed}>
     */
    private static function buildChanges(?array $old, ?array $new): array
    {
        $old = $old ?? [];
        $new = $new ?? [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        $ignored = [
            'updated_at', 'created_at', 'password', 'remember_token',
        ];
        $changes = [];
        foreach ($keys as $key) {
            if (in_array($key, $ignored, true)) {
                continue;
            }
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal === $newVal) {
                continue;
            }
            $label = Str::headline(str_replace('_', ' ', (string) $key));
            $changes[] = [
                'field_name' => $key,
                'field_label' => $label,
                'old_value' => self::stringifyAuditValue($oldVal),
                'new_value' => self::stringifyAuditValue($newVal),
            ];
        }

        return $changes;
    }

    private static function stringifyAuditValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }

    public function index(Request $request, string $module, string $id)
    {
        $orgId = $request->user()->organization_id;

        // Resolve Module string properly
        $resolvedModule = ucfirst($module);
        if (!isset($this->moduleToModelMap[$resolvedModule])) {
            if (isset($this->moduleToModelMap[$module])) {
                $resolvedModule = $module;
            } else {
                // Accept PascalCase module names that match Auditable class_basename
                $candidate = Str::studly($module);
                if (isset($this->moduleToModelMap[$candidate])) {
                    $resolvedModule = $candidate;
                } else {
                    return $this->error("Invalid module '{$module}'.", null, null, null, 400);
                }
            }
        }

        if (isset($this->modelClassMap[$resolvedModule])) {
            $record = $this->modelClassMap[$resolvedModule]::where('organization_id', $orgId)->find($id);
            if (! $record) {
                return $this->error('Record not found.', null, null, null, 404);
            }
            if (! empty($record->branch_id)) {
                try {
                    if ($resolvedModule === 'BranchTransfer') {
                        BranchAccess::assertCanAccessTransferDestination(
                            $request->user(),
                            (string) $record->branch_id
                        );
                    } else {
                        BranchAccess::assertCanAccessBranch($request->user(), (string) $record->branch_id);
                    }
                } catch (\RuntimeException $e) {
                    return $this->error($e->getMessage(), null, null, null, 403);
                }
            }
        }

        $logs = AuditLog::with('user')
            ->where('organization_id', $orgId)
            ->where('module', $resolvedModule)
            ->where('record_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $userName = $log->user
                    ? trim(($log->user->first_name ?? '') . ' ' . ($log->user->last_name ?? ''))
                    : 'System';
                if ($userName === '') {
                    $userName = $log->user?->email ?? 'System';
                }
                $eventType = self::eventTypeForUi((string) $log->event);
                $changes = self::buildChanges(
                    is_array($log->old_values) ? $log->old_values : null,
                    is_array($log->new_values) ? $log->new_values : null
                );

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'event_type' => $eventType,
                    'label' => 'Record ' . $log->event,
                    'action_by' => [
                        'id' => $log->user ? $log->user->id : null,
                        'name' => $userName,
                        'label' => $userName,
                    ],
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'changes' => $changes,
                    'timestamp' => $log->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return $this->success($logs, "Audit logs fetched successfully.");
    }
}
