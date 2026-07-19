<?php

namespace App\Services\CRM;

use App\Models\BKModel;
use App\Models\ModuleRelationFields;
use App\Models\CrmField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Relationship Service
 * 
 * Handles all relationship operations by reading from module_relation_fields table.
 * This service automatically processes belongs_to, has_many, and many_to_many relationships.
 */
class RelationshipService
{
    /**
     * Process all relationships for a model
     */
    public static function processRelationships(
        BKModel $model,
        array $relatedRecords = []
    ): void {
        $module = $model->getModuleName();
        $isNew = !$model->exists;

        // Validate belongs_to relationships
        self::validateBelongsToRelationships($model, $module, $isNew);

        // Process has_many relationships (child records)
        self::processHasManyRelationships($model, $module, $relatedRecords, $isNew);

        // Process many_to_many relationships (pivot tables)
        self::processManyToManyRelationships($model, $module, $relatedRecords, $isNew);
    }

    /**
     * Validate belongs_to relationships
     */
    protected static function validateBelongsToRelationships(
        BKModel $model,
        string $module,
        bool $isNew
    ): void {
        $relations = ModuleRelationFields::where('modulename', $module)
            ->where('deleted', 0)
            ->get()
            ->groupBy('field_id');

        foreach ($relations as $relationGroup) {
            $relation = $relationGroup->first();

            $crmField = CrmField::where('id', $relation->field_id)
                ->where('deleted', 0)
                ->first();

            if (!$crmField) {
                $crmField = CrmField::where('modulename', $module)
                    ->where('fieldname', $relation->field_id)
                    ->where('deleted', 0)
                    ->first();
            }

            if (!$crmField) {
                continue;
            }

            $fieldName = $crmField->fieldname;
            $foreignKeyValue = $model->getAttribute($fieldName);

            if (empty($foreignKeyValue)) {
                continue;
            }

            $relatedModules = $relationGroup->pluck('related_module')->unique()->values()->all();
            $fieldType = strtolower((string) ($crmField->fieldtype ?? ''));

            $isMultiTarget = count($relatedModules) > 1
                || in_array($fieldType, ['multirelationpicklist', 'relationpicklist'], true);

            if ($isMultiTarget) {
                self::validateRelatedRecordPolymorphic($relatedModules, (string) $foreignKeyValue, $fieldName);
            } else {
                self::validateRelatedRecord($relatedModules[0], (string) $foreignKeyValue, $fieldName);
            }
        }
    }

    /**
     * For relationPickList / multiRelationPicklist (or multiple MRF rows per field), accept FK if it exists in any target module.
     */
    protected static function validateRelatedRecordPolymorphic(
        array $relatedModules,
        string $relatedId,
        string $fieldName
    ): void {
        foreach ($relatedModules as $relatedModule) {
            $relatedClass = self::getModelClass($relatedModule);
            if (!class_exists($relatedClass)) {
                continue;
            }
            if ($relatedClass::where('id', $relatedId)->exists()) {
                return;
            }
        }

        foreach ($relatedModules as $relatedModule) {
            $relatedClass = self::getModelClass($relatedModule);
            if (!class_exists($relatedClass)) {
                continue;
            }
            $bare = $relatedClass::withoutGlobalScopes()->where('id', $relatedId)->first();

            if (!$bare) {
                continue;
            }

            if (isset($bare->deleted) && (int) $bare->deleted === 1) {
                throw new \Exception(
                    "The selected " . str_replace('_', ' ', $fieldName) . " does not exist."
                );
            }

            throw new \Exception(
                "The selected " . str_replace('_', ' ', $fieldName) . " belongs to another organization."
            );
        }

        throw new \Exception(
            "The selected " . str_replace('_', ' ', $fieldName) . " does not exist."
        );
    }

    /**
     * Validate that a related record exists and belongs to the same organization
     */
    protected static function validateRelatedRecord(
        string $relatedModule,
        string $relatedId,
        string $fieldName
    ): void {
        $relatedClass = self::getModelClass($relatedModule);

        if (!class_exists($relatedClass)) {
            return; // Module doesn't exist, skip validation
        }

        // Check if record exists in current organization scope
        if (!$relatedClass::where('id', $relatedId)->exists()) {
            $bare = $relatedClass::withoutGlobalScopes()->where('id', $relatedId)->first();

            if (!$bare) {
                throw new \Exception(
                    "The selected " . str_replace('_', ' ', $fieldName) . " does not exist."
                );
            }

            if (isset($bare->deleted) && (int) $bare->deleted === 1) {
                throw new \Exception(
                    "The selected " . str_replace('_', ' ', $fieldName) . " does not exist."
                );
            }

            throw new \Exception(
                "The selected " . str_replace('_', ' ', $fieldName) . " belongs to another organization."
            );
        }
    }

    /**
     * Process has_many relationships (child records like invoice_items)
     */
    protected static function processHasManyRelationships(
        BKModel $model,
        string $module,
        array $relatedRecords,
        bool $isNew
    ): void {
        // Find all modules that have a belongs_to relationship pointing to this module
        $childRelations = ModuleRelationFields::where('related_module', $module)
            ->where('deleted', 0)
            ->get()
            ->groupBy('modulename');

        foreach ($childRelations as $childModule => $relations) {
            // Get the relation name (e.g., 'invoice_items' from 'InvoiceItem')
            $relationName = self::getRelationName($childModule);

            // Skip if no records provided for this relationship
            if (empty($relatedRecords[$relationName])) {
                continue;
            }

            // Get the foreign key field
            $relation = $relations->first();
            $crmField = CrmField::where('id', $relation->field_id)
                ->where('deleted', 0)
                ->first();

            if (!$crmField) {
                continue;
            }

            $foreignKey = $crmField->fieldname;
            $apiFieldName = $crmField->apifieldname ?? lcfirst(str_replace('_', '', ucwords($foreignKey, '_')));
            $childClass = self::getModelClass($childModule);

            if (!class_exists($childClass)) {
                continue;
            }

            $childTable = (new $childClass)->getTable();
            // Dynamic/custom fields are stored outside base module tables.
            // Skip stale relation metadata that points to non-physical columns.
            if (!Schema::hasColumn($childTable, $foreignKey)) {
                continue;
            }

            // Get existing child IDs (for updates)
            $existingIds = collect();
            if (!$isNew) {
                $existingIds = $childClass::where($foreignKey, $model->id)
                    ->pluck('id');
            }

            $submittedIds = collect();

            // Process each related record
            foreach ($relatedRecords[$relationName] as $record) {
                // Set the foreign key (both DB name and API name for fill/validation)
                $record[$foreignKey] = $model->id;
                $record[$apiFieldName] = $model->id;

                // Normalize field names (camelCase to snake_case)
                $record = self::normalizeFieldNames($record, $foreignKey);

                $itemId = $record['id'] ?? null;

                // For new parent records, always create new children
                if ($isNew) {
                    $itemId = null;
                }

                // Generate UUID if new
                if (!$itemId) {
                    $record['id'] = (string) Str::uuid();
                } else {
                    $submittedIds->push($itemId);
                }

                // Create or update the related model
                $relatedModel = RecordObject::make(
                    $childModule,
                    $itemId,
                    $record,
                    'EditView'
                );

                $relatedModel->save();
            }

            // Delete orphaned records (existing but not in submitted list)
            if (!$isNew) {
                $idsToDelete = $existingIds->diff($submittedIds);
                if ($idsToDelete->isNotEmpty()) {
                    $childClass::whereIn('id', $idsToDelete->all())->delete();
                }
            }
        }
    }

    /**
     * Process many_to_many relationships (pivot tables like comment_rel)
     */
    protected static function processManyToManyRelationships(
        BKModel $model,
        string $module,
        array $relatedRecords,
        bool $isNew
    ): void {
        // Handle known pivot tables
        $pivotTables = [
            'comment_rel' => [
                'parent_key' => 'comment_id',
                'related_key' => 'parent_id',
                'parent_module_column' => 'parent_module',
                'polymorphic' => true,
            ],
            'activity_relations' => [
                'parent_key' => 'activity_id',
                'related_key' => 'entity_id',
                'parent_module_column' => 'entity_type',
                'polymorphic' => true,
            ],
        ];

        foreach ($pivotTables as $pivotTable => $config) {
            $relationName = str_replace('_', '', $pivotTable); // comment_rel -> commentrel
            $records = $relatedRecords[$relationName] ?? $relatedRecords[$pivotTable] ?? [];

            // Special handling for Activity: if relatedRecords is a direct array and module is Activity
            if ($module === 'Activity' && empty($records) && !empty($relatedRecords) && is_array($relatedRecords) && isset($relatedRecords[0])) {
                // Check if it's the Activity format (has entityType, entityId, relationType)
                if (isset($relatedRecords[0]['entityType']) || isset($relatedRecords[0]['entityId'])) {
                    $records = $relatedRecords;
                }
            }

            if (empty($records)) {
                continue;
            }

            foreach ($records as $relation) {
                $rawRelatedId = $relation[$config['related_key']]
                    ?? $relation[str_replace('_', '', $config['related_key'])] // camelCase variant (entity_id -> entityId)
                    ?? $relation['entityId'] // Direct entityId
                    ?? null;

                $relatedModule = $config['polymorphic']
                    ? ($relation[$config['parent_module_column']] 
                        ?? $relation[lcfirst(str_replace('_', '', ucwords($config['parent_module_column'], '_')))] // camelCase (entity_type -> entityType)
                        ?? $relation['entityType'] // Direct entityType
                        ?? $module) // fallback to current module
                    : null;

                if (!$rawRelatedId || !$relatedModule) {
                    continue;
                }

                if ($pivotTable === 'activity_relations') {
                    $relatedModule = self::normalizeEntityType($relatedModule);
                }

                // Normalize related IDs:
                // - Some clients send `entityId` as array of strings: ["uuid1", "uuid2"]
                // - Or array of objects: [{value: "uuid", label: "..."}, ...]
                // - Or single object: {value: "uuid", label: "..."}
                $relatedIds = self::extractEntityIds($rawRelatedId);

                // For polymorphic, determine the parent key value
                // For Activity, use model->id as activity_id
                $parentKeyValue = null;
                if ($config['parent_key'] === 'comment_id') {
                    $parentKeyValue = $relation['comment_id'] ?? $relation['commentId'] ?? null;
                } elseif ($config['parent_key'] === 'activity_id') {
                    // For Activity, always use the model's ID
                    $parentKeyValue = $model->id;
                }

                if ($config['polymorphic'] && $parentKeyValue) {
                    // `activity_relations.relation_type` is NOT NULL. Skip inserts that don't include it.
                    if ($pivotTable === 'activity_relations' && !isset($relation['relationType']) && !isset($relation['relation_type'])) {
                        continue;
                    }

                    foreach ($relatedIds as $relatedId) {
                        $relatedId = (string) $relatedId;

                    // Check if record already exists
                    $exists = DB::table($pivotTable)
                        ->where($config['parent_key'], $parentKeyValue)
                        ->where($config['related_key'], $relatedId)
                        ->where($config['parent_module_column'], $relatedModule)
                        ->exists();

                    // Check if table has organization_id column
                    $hasOrgId = DB::getSchemaBuilder()->hasColumn($pivotTable, 'organization_id');

                    // Prepare insert/update data
                    $insertData = [
                        $config['parent_key'] => $parentKeyValue,
                        $config['related_key'] => $relatedId,
                        $config['parent_module_column'] => $relatedModule,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Add organization_id only if column exists
                    if ($hasOrgId) {
                        $insertData['organization_id'] = auth()->user()->organization_id ?? null;
                    }

                    // Add relation_type if provided (for activity_relations)
                    if ($pivotTable === 'activity_relations' && isset($relation['relationType'])) {
                        $insertData['relation_type'] = $relation['relationType'];
                    } elseif ($pivotTable === 'activity_relations' && isset($relation['relation_type'])) {
                        $insertData['relation_type'] = $relation['relation_type'];
                    }

                    // Add ID only for new records
                    if (!$exists) {
                        $insertData['id'] = (string) Str::uuid();
                    }

                    // Insert/update pivot record
                    DB::table($pivotTable)->updateOrInsert(
                        [
                            $config['parent_key'] => $parentKeyValue,
                            $config['related_key'] => $relatedId,
                            $config['parent_module_column'] => $relatedModule,
                        ],
                        $insertData
                    );
                    }
                } elseif (!$config['polymorphic']) {
                    // Non-polymorphic pivot
                    foreach ($relatedIds as $relatedId) {
                        $relatedId = (string) $relatedId;
                        DB::table($pivotTable)->updateOrInsert(
                            [
                                $config['parent_key'] => $model->id,
                                $config['related_key'] => $relatedId,
                            ],
                            [
                                'id' => (string) Str::uuid(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }
        }
    }

    /**
     * Load related records for a model
     */
    public static function loadRelatedRecords(BKModel $model): array
    {
        $module = $model->getModuleName();
        $relatedRecords = [];

        // Load has_many relationships
        $relatedRecords = array_merge(
            $relatedRecords,
            self::loadHasManyRelationships($model, $module)
        );

        // Load many_to_many relationships
        $relatedRecords = array_merge(
            $relatedRecords,
            self::loadManyToManyRelationships($model, $module)
        );

        return $relatedRecords;
    }

    /**
     * When module_relation_fields rows are missing, pick the FK column from relationPickList /
     * multiRelationPicklist definitions (same rules as RelatedRecords fallback).
     */
    public static function resolveRelationCrmFieldForParentChild(
        string $parentModule,
        string $childModule,
        string $childTable,
        ?string $orgId
    ): ?CrmField {
        $candidates = CrmField::where('modulename', $childModule)
            ->whereIn('fieldtype', ['relationPickList', 'multiRelationPicklist'])
            ->where('deleted', 0)
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', 'default');
                if ($orgId !== null) {
                    $q->orWhere('organization_id', $orgId);
                }
            })
            ->orderBy('seq')
            ->get();

        $byColumn = [];
        foreach ($candidates as $field) {
            if (Schema::hasColumn($childTable, $field->fieldname)) {
                $byColumn[$field->fieldname] = $field;
            }
        }

        if ($byColumn === []) {
            return null;
        }

        $snakeParentId = Str::snake($parentModule) . '_id';
        if (isset($byColumn[$snakeParentId])) {
            return $byColumn[$snakeParentId];
        }
        if (isset($byColumn['customer_id'])) {
            return $byColumn['customer_id'];
        }

        $first = reset($byColumn);

        return $first ?: null;
    }

    /**
     * Load has_many relationships
     */
    protected static function loadHasManyRelationships(BKModel $model, string $module): array
    {
        $relatedRecords = [];
        $orgId = auth()->user()->organization_id ?? null;

        // Find all modules that have a belongs_to relationship pointing to this module
        $childRelations = ModuleRelationFields::where('related_module', $module)
            ->where('deleted', 0)
            ->get()
            ->groupBy('modulename');

        $handledChildModules = [];

        foreach ($childRelations as $childModule => $relations) {
            $handledChildModules[$childModule] = true;
            $relation = $relations->first();
            $crmField = CrmField::where('id', $relation->field_id)
                ->where('deleted', 0)
                ->first();

            if (!$crmField) {
                continue;
            }

            $foreignKey = $crmField->fieldname;
            $childClass = self::getModelClass($childModule);

            if (!class_exists($childClass)) {
                continue;
            }

            $childTable = (new $childClass)->getTable();
            // Skip stale metadata/custom fields that do not exist in the child table.
            if (!Schema::hasColumn($childTable, $foreignKey)) {
                continue;
            }

            // Get child record IDs with organization filtering
            $childIds = $childClass::where($foreignKey, $model->id)
                ->when(Schema::hasColumn($childTable, 'deleted'), function ($q) use ($childTable) {
                    $q->where($childTable . '.deleted', 0);
                })
                ->when($orgId, function ($q) use ($childClass, $orgId) {
                    $table = (new $childClass)->getTable();
                    if (DB::getSchemaBuilder()->hasColumn($table, 'organization_id')) {
                        $q->where('organization_id', $orgId);
                    }
                })
                ->pluck('id');

            $relationName = self::getRelationName($childModule);
            $relatedItems = [];

            foreach ($childIds as $childId) {
                try {
                    $childRecord = RecordObject::make($childModule, $childId)
                        ->transformToApiFormat();
                    $relatedItems[] = $childRecord;
                } catch (\Exception $e) {
                    // Skip records that can't be loaded
                    continue;
                }
            }

            $relatedRecords[$relationName] = $relatedItems;
        }

        // Fallback: relation fields in crm_fields without module_relation_fields rows for this parent
        $childModulesWithRelationFields = CrmField::whereIn('fieldtype', ['relationPickList', 'multiRelationPicklist'])
            ->where('deleted', 0)
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', 'default');
                if ($orgId !== null) {
                    $q->orWhere('organization_id', $orgId);
                }
            })
            ->distinct()
            ->pluck('modulename');

        foreach ($childModulesWithRelationFields as $childModule) {
            if (!empty($handledChildModules[$childModule])) {
                continue;
            }

            $childClass = self::getModelClass($childModule);
            if (!class_exists($childClass)) {
                continue;
            }

            $childTable = (new $childClass)->getTable();
            $crmField = self::resolveRelationCrmFieldForParentChild($module, $childModule, $childTable, $orgId);
            if (!$crmField) {
                continue;
            }

            $foreignKey = $crmField->fieldname;
            if (!Schema::hasColumn($childTable, $foreignKey)) {
                continue;
            }

            $handledChildModules[$childModule] = true;

            $childIds = $childClass::where($foreignKey, $model->id)
                ->when(Schema::hasColumn($childTable, 'deleted'), function ($q) use ($childTable) {
                    $q->where($childTable . '.deleted', 0);
                })
                ->when($orgId, function ($q) use ($childClass, $orgId) {
                    $table = (new $childClass)->getTable();
                    if (DB::getSchemaBuilder()->hasColumn($table, 'organization_id')) {
                        $q->where('organization_id', $orgId);
                    }
                })
                ->pluck('id');

            $relationName = self::getRelationName($childModule);
            $relatedItems = [];

            foreach ($childIds as $childId) {
                try {
                    $childRecord = RecordObject::make($childModule, $childId)
                        ->transformToApiFormat();
                    $relatedItems[] = $childRecord;
                } catch (\Exception $e) {
                    continue;
                }
            }

            $relatedRecords[$relationName] = $relatedItems;
        }

        // Parent → aggregate via junction (e.g. Product → Quotation via QuotationItem when FK is only on line items).
        if ($module === 'Product') {
            foreach (self::loadIndirectAggregateRelatedForDetail($module, (string) $model->id, $orgId) as $relationKey => $items) {
                $relatedRecords[$relationKey] = $items;
            }
        }

        return $relatedRecords;
    }

    /**
     * Load many_to_many relationships
     */
    protected static function loadManyToManyRelationships(BKModel $model, string $module): array
    {
        $relatedRecords = [];

        // Handle comment_rel (polymorphic)
        $commentRels = DB::table('comment_rel')
            ->where('parent_id', $model->id)
            ->where('parent_module', $module)
            ->get();

        if ($commentRels->isNotEmpty()) {
            $relatedRecords['comment_rel'] = [];
            foreach ($commentRels as $rel) {
                try {
                    $comment = RecordObject::make('Comment', $rel->comment_id)
                        ->transformToApiFormat();
                    $relatedRecords['comment_rel'][] = $comment;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Handle activity_relations (polymorphic)
        $activityRels = DB::table('activity_relations')
            ->where('entity_id', $model->id)
            ->where('entity_type', $module)
            ->get();

        if ($activityRels->isNotEmpty()) {
            $relatedRecords['activity_relations'] = [];
            foreach ($activityRels as $rel) {
                try {
                    $activity = RecordObject::make('Activity', $rel->activity_id)
                        ->transformToApiFormat();
                    $relatedRecords['activity_relations'][] = $activity;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return $relatedRecords;
    }

    /**
     * Get model class name for a module
     */
    protected static function getModelClass(string $module): string
    {
        return "\\App\\Modules\\Api\\V1\\{$module}\\Models\\{$module}";
    }

    /**
     * Convert module name to relation name (e.g., 'InvoiceItem' -> 'invoice_items')
     */
    protected static function getRelationName(string $module): string
    {
        // Convert PascalCase to snake_case and pluralize
        $snakeCase = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $module));
        return Str::plural($snakeCase);
    }

    /**
     * Resolve DB column name for a module_relation_fields row (crm_fields.fieldname by field_id, or literal column if seeded).
     */
    protected static function resolveMrfFieldName(ModuleRelationFields $rel): ?string
    {
        $cf = CrmField::where('id', $rel->field_id)->where('deleted', 0)->first();
        if ($cf) {
            return $cf->fieldname;
        }

        $class = self::getModelClass($rel->modulename);
        if (!class_exists($class)) {
            return null;
        }

        $table = (new $class())->getTable();
        $fid = $rel->field_id;
        if (is_string($fid) && Schema::hasColumn($table, $fid)) {
            return $fid;
        }

        return null;
    }

    /**
     * API field name (camelCase) for a DB column on a module, for meta.relatedField.
     */
    protected static function resolveApiFieldNameForDbField(string $moduleName, string $fieldname): string
    {
        $cf = CrmField::where('modulename', $moduleName)->where('fieldname', $fieldname)->where('deleted', 0)->first();
        if ($cf && !empty($cf->apifieldname)) {
            return trim((string) $cf->apifieldname);
        }

        return Str::camel($fieldname);
    }

    /**
     * Find a junction module J that links parentModule and aggregateModule: J has FK to parent and FK to aggregate.
     *
     * @return array{junction: string, parentFk: string, aggregateFk: string, via: string}|null
     */
    protected static function findJunctionPathToAggregate(string $parentModule, string $aggregateModule): ?array
    {
        $junctionNames = ModuleRelationFields::where('related_module', $parentModule)
            ->where('deleted', 0)
            ->distinct()
            ->pluck('modulename')
            ->all();

        foreach ($junctionNames as $J) {
            $toParent = ModuleRelationFields::where('modulename', $J)
                ->where('related_module', $parentModule)
                ->where('deleted', 0)
                ->first();
            $toAggregate = ModuleRelationFields::where('modulename', $J)
                ->where('related_module', $aggregateModule)
                ->where('deleted', 0)
                ->first();

            if (!$toParent || !$toAggregate) {
                continue;
            }

            // A junction must be a separate table (e.g. line items). If J === aggregate, the
            // two MRF rows are unrelated FKs on one row (e.g. customer_id + parent_quotation_id
            // on Quotation), and pluck(aggregateFk) returns the wrong semantics for related lists.
            if ($J === $aggregateModule) {
                continue;
            }

            $parentFk = self::resolveMrfFieldName($toParent);
            $aggregateFk = self::resolveMrfFieldName($toAggregate);
            if (!$parentFk || !$aggregateFk) {
                continue;
            }

            $junctionClass = self::getModelClass($J);
            if (!class_exists($junctionClass)) {
                continue;
            }

            $jTable = (new $junctionClass())->getTable();
            if (!Schema::hasColumn($jTable, $parentFk) || !Schema::hasColumn($jTable, $aggregateFk)) {
                continue;
            }

            if (!class_exists(self::getModelClass($aggregateModule))) {
                continue;
            }

            return [
                'junction' => $J,
                'parentFk' => $parentFk,
                'aggregateFk' => $aggregateFk,
                'via' => $J,
            ];
        }

        return null;
    }

    /**
     * Aggregate module names reachable from parentModule via at least one valid junction path (skips Organization/User/parent).
     *
     * @return list<string>
     */
    protected static function findAggregateModulesWithJunctionPath(string $parentModule): array
    {
        $junctionNames = ModuleRelationFields::where('related_module', $parentModule)
            ->where('deleted', 0)
            ->distinct()
            ->pluck('modulename')
            ->all();

        $skip = [$parentModule, 'Organization', 'User'];
        $found = [];

        foreach ($junctionNames as $J) {
            $candidates = ModuleRelationFields::where('modulename', $J)
                ->where('deleted', 0)
                ->whereNotIn('related_module', $skip)
                ->pluck('related_module')
                ->unique();

            foreach ($candidates as $agg) {
                if (self::findJunctionPathToAggregate($parentModule, $agg)) {
                    $found[$agg] = true;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * For GET .../{parent}/{id}/{aggregate}/records when the FK from parent exists only on a junction/line-item module.
     *
     * @return array{records: array<int, array>, via: string, relatedField: string}|null
     */
    public static function resolveIndirectAggregateRelatedList(
        string $parentModule,
        string $relatedModule,
        string $parentId,
        ?string $orgId
    ): ?array {
        $path = self::findJunctionPathToAggregate($parentModule, $relatedModule);
        if (!$path) {
            return null;
        }

        return self::fetchAggregateViaJunctionPath($relatedModule, $path, $parentId, $orgId);
    }

    /**
     * @return array<string, array<int, array>> relation key (snake plural) => API records
     */
    public static function loadIndirectAggregateRelatedForDetail(string $parentModule, string $parentId, ?string $orgId): array
    {
        $out = [];

        foreach (self::findAggregateModulesWithJunctionPath($parentModule) as $aggregateModule) {
            $path = self::findJunctionPathToAggregate($parentModule, $aggregateModule);
            if (!$path) {
                continue;
            }

            $payload = self::fetchAggregateViaJunctionPath($aggregateModule, $path, $parentId, $orgId);
            $out[self::getRelationName($aggregateModule)] = $payload['records'];
        }

        return $out;
    }

    /**
     * @param  array{junction: string, parentFk: string, aggregateFk: string, via: string}  $path
     * @return array{records: array<int, array>, via: string, relatedField: string}
     */
    protected static function fetchAggregateViaJunctionPath(
        string $aggregateModule,
        array $path,
        string $parentId,
        ?string $orgId
    ): array {
        $junctionModule = $path['junction'];
        $parentFk = $path['parentFk'];
        $aggregateFk = $path['aggregateFk'];

        $junctionClass = self::getModelClass($junctionModule);
        $aggregateClass = self::getModelClass($aggregateModule);

        $relatedFieldApi = self::resolveApiFieldNameForDbField($junctionModule, $parentFk);

        if (!class_exists($junctionClass) || !class_exists($aggregateClass)) {
            return [
                'records' => [],
                'via' => $path['via'],
                'relatedField' => $relatedFieldApi,
            ];
        }

        $junction = new $junctionClass();
        $jTable = $junction->getTable();

        $query = $junctionClass::where($parentFk, $parentId);
        if ($orgId !== null && Schema::hasColumn($jTable, 'organization_id')) {
            $query->where('organization_id', $orgId);
        }
        if (Schema::hasColumn($jTable, 'deleted')) {
            $query->where('deleted', 0);
        }

        $aggregateIds = $query->distinct()->pluck($aggregateFk)->filter()->values()->all();

        if ($aggregateIds === []) {
            return [
                'records' => [],
                'via' => $path['via'],
                'relatedField' => $relatedFieldApi,
            ];
        }

        $agg = new $aggregateClass();
        $aTable = $agg->getTable();
        $q = $aggregateClass::whereIn('id', $aggregateIds);
        if ($orgId !== null && Schema::hasColumn($aTable, 'organization_id')) {
            $q->where('organization_id', $orgId);
        }
        if (Schema::hasColumn($aTable, 'deleted')) {
            $q->where('deleted', 0);
        }
        if (Schema::hasColumn($aTable, 'created_at')) {
            $q->orderByDesc('created_at');
        }

        $records = [];
        foreach ($q->get() as $row) {
            try {
                $records[] = RecordObject::make($aggregateModule, $row->id)->transformToApiFormat();
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [
            'records' => $records,
            'via' => $path['via'],
            'relatedField' => $relatedFieldApi,
        ];
    }

    /**
     * Normalize field names (e.g., invoiceId -> invoice_id)
     */
    protected static function normalizeFieldNames(array $record, string $foreignKey): array
    {
        // Convert camelCase to snake_case for foreign keys
        $camelCaseKey = lcfirst(str_replace('_', '', ucwords($foreignKey, '_')));
        
        if (isset($record[$camelCaseKey]) && !isset($record[$foreignKey])) {
            $record[$foreignKey] = $record[$camelCaseKey];
            unset($record[$camelCaseKey]);
        }

        return $record;
    }

    /**
     * Normalize common entity_type typos for activity relations
     */
    protected static function normalizeEntityType(string $entityType): string
    {
        $normalized = trim($entityType);
        $map = [
            'Quotaion' => 'Quotation',
            'quotaion' => 'Quotation',
        ];

        return $map[$normalized] ?? $normalized;
    }

    /**
     * Extract entity IDs from various client formats:
     * - string or int: ["uuid"]
     * - array of strings: ["uuid1", "uuid2"]
     * - object with value: {value: "uuid", label: "..."} -> ["uuid"]
     * - array of objects: [{value: "uuid", label: "..."}, ...] -> ["uuid1", "uuid2"]
     */
    protected static function extractEntityIds(mixed $rawRelatedId): array
    {
        if ($rawRelatedId === null || $rawRelatedId === '') {
            return [];
        }

        $items = is_array($rawRelatedId) ? $rawRelatedId : [$rawRelatedId];
        $ids = [];

        foreach ($items as $item) {
            if (is_string($item) || is_int($item)) {
                $ids[] = (string) $item;
            } elseif (is_array($item) && isset($item['value'])) {
                $val = $item['value'];
                if (is_string($val) || is_int($val)) {
                    $ids[] = (string) $val;
                }
            } elseif (is_object($item) && isset($item->value)) {
                $val = $item->value;
                if (is_string($val) || is_int($val)) {
                    $ids[] = (string) $val;
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Get all belongs_to relationships for a module
     */
    public static function getBelongsToRelationships(string $module): array
    {
        $relations = ModuleRelationFields::where('modulename', $module)
            ->where('deleted', 0)
            ->get();

        return $relations->map(function ($relation) {
            $crmField = CrmField::where('id', $relation->field_id)
                ->where('deleted', 0)
                ->first();

            return [
                'field_id' => $relation->field_id,
                'field_name' => $crmField->fieldname ?? null,
                'related_module' => $relation->related_module,
            ];
        })->toArray();
    }

    /**
     * Get all has_many relationships for a module (modules that reference this module)
     */
    public static function getHasManyRelationships(string $module): array
    {
        $relations = ModuleRelationFields::where('related_module', $module)
            ->where('deleted', 0)
            ->get()
            ->groupBy('modulename');

        return $relations->map(function ($relations) {
            $relation = $relations->first();
            $crmField = CrmField::where('id', $relation->field_id)
                ->where('deleted', 0)
                ->first();

            $foreignKey = $crmField->fieldname ?? null;
            $childClass = self::getModelClass($relation->modulename);
            if (!$foreignKey || !class_exists($childClass)) {
                return null;
            }
            $childTable = (new $childClass)->getTable();
            if (!Schema::hasColumn($childTable, $foreignKey)) {
                return null;
            }

            return [
                'module' => $relation->modulename,
                'foreign_key' => $foreignKey,
                'field_id' => $relation->field_id,
            ];
        })->filter()->values()->toArray();
    }
}
