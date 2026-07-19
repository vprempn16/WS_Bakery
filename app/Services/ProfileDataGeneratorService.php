<?php

namespace App\Services;

use App\Modules\Api\V1\Profile\Models\SystemAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProfileDataGeneratorService
{
    /** @var array<int|string, string>|null */
    private ?array $actionsMap = null;

    public function generate(string|int $profileId, string $organizationId, ?object $profileRow = null): array
    {
        if ($profileRow === null) {
            $profileDetails = DB::table('profiles')
                ->where('id', $profileId)
                ->select('id', 'name', 'description', 'status', 'organization_id')
                ->first();
        } else {
            $profileDetails = $profileRow;
        }

        if (!$profileDetails) {
            return [];
        }

        if ((string) ($profileDetails->organization_id ?? '') !== (string) $organizationId) {
            return [];
        }

        $profileArray = [
            'name' => $profileDetails->name,
            'description' => $profileDetails->description,
            'status' => $profileDetails->status,
            'modules' => [],
        ];

        if ($this->actionsMap === null) {
            $this->actionsMap = SystemAction::pluck('action_key', 'id')->toArray();
        }

        $actionRows = DB::table('profile_module_actions')
            ->where('profileid', $profileId)
            ->get();

        foreach ($actionRows as $row) {
            $actionKey = $this->actionsMap[$row->action_id] ?? ('action_' . $row->action_id);
            $profileArray['modules'][$row->modulename]['permissions'][$actionKey] = (int) $row->permission;
        }

        $fieldRows = DB::table('profile_module_fields')
            ->where('profileid', $profileId)
            ->get();

        foreach ($fieldRows as $fr) {
            $profileArray['modules'][$fr->modulename]['fields'][$fr->field_id] = [
                'invisible' => (int) $fr->invisible,
                'readonly' => (int) $fr->readonly,
                'editable' => (int) $fr->editable,
            ];
        }

        $profilesDir = base_path('Profiles');
        if (!File::exists($profilesDir)) {
            File::makeDirectory($profilesDir, 0755, true);
        }

        $orgProfilesDir = $profilesDir . DIRECTORY_SEPARATOR . $organizationId;
        if (!File::exists($orgProfilesDir)) {
            File::makeDirectory($orgProfilesDir, 0755, true);
        }

        $filePath = $orgProfilesDir . DIRECTORY_SEPARATOR . "{$profileId}_Profile.php";
        File::put(
            $filePath,
            "<?php\n\nreturn " . var_export($profileArray, true) . ";\n"
        );

        return $profileArray;
    }
}
