<?php

namespace App\Modules\Api\V1\BranchSales\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BranchDailyReportResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'organizationId' => $this->organization_id,
            'branchId' => $this->branch_id,
            'branchId_label' => $this->branch ? $this->branch->name : null,
            'reportDate' => $this->report_date ? $this->report_date->format('Y-m-d') : null,
            'totalRevenue' => (float) $this->total_revenue,
            'totalWasteAmount' => (float) $this->total_waste_amount,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'items' => BranchDailyReportItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
