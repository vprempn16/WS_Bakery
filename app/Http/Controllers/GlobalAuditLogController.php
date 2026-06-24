<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Modules\Api\V1\AuditLog\Models\AuditLog;

class GlobalAuditLogController extends Controller
{
    private array $moduleToModelMap = [
        'User' => 'User',
        'Vendor' => 'Vendor',
        'Ingredient' => 'Ingredient',
        'InventoryTransaction' => 'InventoryTransaction',
        'Product' => 'Product',
        'Recipe' => 'Recipe',
        'Branch' => 'Branch',
        'ProductionBatch' => 'ProductionBatch',
        'BranchStock' => 'BranchStock',
        'BranchTransfer' => 'BranchTransfer',
        'BranchDailyReport' => 'BranchDailyReport',
    ];

    public function index(Request $request, string $module, string $id)
    {
        $orgId = $request->user()->organization_id;

        // Resolve Module string properly
        $resolvedModule = ucfirst($module);
        if (!isset($this->moduleToModelMap[$resolvedModule])) {
            if (isset($this->moduleToModelMap[$module])) {
                $resolvedModule = $module;
            } else {
                return $this->error("Invalid module '{$module}'.", null, null, null, 400);
            }
        }

        $logs = AuditLog::with('user')
            ->where('organization_id', $orgId)
            ->where('module', $resolvedModule)
            ->where('record_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                $userName = $log->user ? trim($log->user->first_name . ' ' . $log->user->last_name) : 'System';
                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => 'Record ' . $log->event,
                    'action_by' => [
                        'id' => $log->user ? $log->user->id : null,
                        'name' => $userName,
                        'label' => $userName
                    ],
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return $this->success($logs, "Audit logs fetched successfully.");
    }
}
