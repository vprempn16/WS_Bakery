<?php

namespace App\Modules\Api\V1\Product\Services;

use App\Modules\Api\V1\Product\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductNumberService
{
    /**
     * Trim and, for purely numeric values, strip leading zeros so 3/03/002 collide.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            return (string) ((int) $trimmed);
        }

        return $trimmed;
    }

    public static function isNumericForm(string $value): bool
    {
        return (bool) preg_match('/^\d+$/', trim($value));
    }

    /**
     * Find an existing product that conflicts with the given product number
     * (exact match, or numeric-equivalent for digit-only numbers).
     */
    public static function findConflict(
        string $organizationId,
        string $productNumber,
        ?string $excludeId = null
    ): ?Product {
        $normalized = self::normalize($productNumber);
        if ($normalized === null) {
            return null;
        }

        $query = Product::query()->where('organization_id', $organizationId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if (self::isNumericForm($productNumber)) {
            $numeric = (int) $normalized;
            $driver = \Illuminate\Support\Facades\DB::getDriverName();
            $query->where(function (Builder $q) use ($normalized, $numeric, $driver) {
                $q->where('product_number', $normalized)
                    ->orWhere(function (Builder $inner) use ($numeric, $driver) {
                        if ($driver === 'sqlite') {
                            $inner->where('product_number', (string) $numeric)
                                ->orWhere('product_number', sprintf('%0' . strlen((string) $numeric) . 'd', $numeric));
                        } else {
                            $inner->whereRaw('product_number REGEXP "^[0-9]+$"')
                                ->whereRaw('CAST(product_number AS UNSIGNED) = ?', [$numeric]);
                        }
                    });
            });
        } else {
            $query->where('product_number', $normalized);
        }

        return $query->first();
    }

    /**
     * @return array{available: bool, message?: string, conflictingProduct?: array{id: string, name: string, productNumber: string}}
     */
    public static function checkAvailability(
        string $organizationId,
        ?string $productNumber,
        ?string $excludeId = null
    ): array {
        $normalized = self::normalize($productNumber);
        if ($normalized === null) {
            return ['available' => true];
        }

        $conflict = self::findConflict($organizationId, (string) $productNumber, $excludeId);
        if (!$conflict) {
            return ['available' => true];
        }

        $label = $conflict->name ?: $conflict->product_number;
        $existingNumber = $conflict->product_number;

        return [
            'available' => false,
            'message' => "Product number already exists for \"{$label}\" (#{$existingNumber})",
            'conflictingProduct' => [
                'id' => (string) $conflict->id,
                'name' => (string) ($conflict->name ?? ''),
                'productNumber' => (string) $existingNumber,
            ],
        ];
    }
}
