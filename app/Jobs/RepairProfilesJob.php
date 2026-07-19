<?php

namespace App\Jobs;

use App\Modules\Api\V1\Profile\Models\Profile;
use App\Services\ProfileDataGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RepairProfilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        protected string $organizationId,
        protected string $type, // 'all' | 'profile'
        protected ?string $profileId = null
    ) {}

    public function handle(ProfileDataGeneratorService $profileGenerator): void
    {
        $orgId = $this->organizationId;

        if ($this->type !== 'profile' && $this->type !== 'all') {
            return;
        }

        if ($this->profileId) {
            $profile = Profile::where('id', $this->profileId)
                ->where('organization_id', $orgId)
                ->where('deleted', 0)
                ->first();
            if ($profile) {
                $profileGenerator->generate($profile->id, $orgId, $profile);
            }
            return;
        }

        Profile::where('organization_id', $orgId)
            ->where('deleted', 0)
            ->orderBy('id')
            ->chunk(100, function ($profiles) use ($profileGenerator, $orgId) {
                foreach ($profiles as $profile) {
                    try {
                        $profileGenerator->generate($profile->id, $orgId, $profile);
                    } catch (\Throwable $e) {
                        Log::warning('RepairProfilesJob: profile regeneration failed', [
                            'profile_id' => $profile->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
