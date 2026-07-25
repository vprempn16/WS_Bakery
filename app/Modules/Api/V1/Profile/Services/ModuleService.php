<?php

namespace App\Modules\Api\V1\Profile\Services;

use App\Models\PortalModule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleService
{
    /**
     * Frontend-visible modules only
     */
    private const FRONTEND_MODULES = [
        'Vendor',
        'Ingredient',
        'Product',
        'Branch',
        'User',
        'Billing',
        'BranchDailyReport',
        'BranchTransfer',
        'BranchStock',
        'InventoryTransaction',
        'ProductionBatch',
        'Recipe',
    ];

    /**
     * Map API route module names (plural) to internal CRM module names (singular).
     */
    public static function resolveName(string $module): string
    {
        $map = [
            'Vendors'               => 'Vendor',
            'Ingredients'           => 'Ingredient',
            'Products'              => 'Product',
            'Branches'              => 'Branch',
            'Users'                 => 'User',
            'Billings'              => 'Billing',
            'BranchDailyReports'    => 'BranchDailyReport',
            'BranchTransfers'       => 'BranchTransfer',
            'BranchStocks'          => 'BranchStock',
            'InventoryTransactions' => 'InventoryTransaction',
            'ProductionBatches'     => 'ProductionBatch',
            'Recipes'               => 'Recipe',
        ];

        return $map[$module] ?? $module;
    }

    /**
     * Returns entity modules that have email fields (from portal_module.is_email = 1).
     */
    public static function getEmailModules(): array
    {
        return Cache::remember('email_modules', now()->addHours(24), function () {
            self::syncPortalModules();

            return PortalModule::where('is_entity', 1)
                ->where('is_email', 1)
                ->where('status', 'Active')
                ->pluck('modulename')
                ->toArray();
        });
    }

    /**
     * Returns entity modules that have phone fields (from portal_module.is_phone = 1).
     */
    public static function getPhoneModules(): array
    {
        return Cache::remember('phone_modules', now()->addHours(24), function () {
            self::syncPortalModules();

            return PortalModule::where('is_entity', 1)
                ->where('is_phone', 1)
                ->where('status', 'Active')
                ->pluck('modulename')
                ->toArray();
        });
    }

    /**
     * Return active entity modules only
     * Used for profile permissions, fields, UI
     */
    public static function getEntityModules(): array
    {
        self::syncPortalModules();

        return PortalModule::where('is_entity', 1)
            ->where('status', 'Active')
            ->where('account_id', 'all')
            ->where('is_system_default', 1)
            ->whereNull('parent_module_id')
            ->whereIn('modulename', self::FRONTEND_MODULES)
            ->orderBy('sort_order')
            ->pluck('modulename')
            ->toArray();
    }

    /**
     * Ensure portal_module entries exist for core modules.
     */
    private static function syncPortalModules(): void
    {
        $seedFile = app_path('Models/BkPortal/PortalModuleSeed.php');
        if (!file_exists($seedFile)) {
            return;
        }

        $modules = include $seedFile;
        if (!is_array($modules)) {
            return;
        }

        // Clean up old CRM seed data if found
        $hasCrm = DB::table('portal_module')->where('modulename', 'Lead')->exists();
        if ($hasCrm) {
            DB::table('portal_module')->truncate();
            Cache::forget('email_modules');
            Cache::forget('phone_modules');
        }

        foreach ($modules as $module) {
            if (empty($module['modulename'])) {
                continue;
            }

            $exists = DB::table('portal_module')
                ->where('modulename', $module['modulename'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('portal_module')->insert([
                'id' => $module['id'] ?? (string) Str::uuid(),
                'modulename' => $module['modulename'],
                'modulelabel' => $module['modulelabel'] ?? $module['modulename'],
                'is_entity' => $module['is_entity'] ?? 1,
                'is_email' => $module['is_email'] ?? 0,
                'is_phone' => $module['is_phone'] ?? 0,
                'status' => $module['status'] ?? 'Active',
                'sort_order' => $module['sort_order'] ?? 0,
                'account_id' => $module['account_id'] ?? 'all',
                'is_system_default' => $module['is_system_default'] ?? 1,
                'parent_module_id' => $module['parent_module_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Return active entity modules as model instances (with all attributes)
     */
    public static function getEntityPortalModules(): \Illuminate\Database\Eloquent\Collection
    {
        self::syncPortalModules();

        return PortalModule::where('is_entity', 1)
            ->where('status', 'Active')
            ->where('account_id', 'all')
            ->where('is_system_default', 1)
            ->whereNull('parent_module_id')
            ->whereIn('modulename', self::FRONTEND_MODULES)
            ->orderBy('sort_order')
            ->orderBy('modulename')
            ->get();
    }
}
