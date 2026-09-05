<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Move flat product images into uploads/images/product/ and update DB paths.
 * Old: uploads/images/{file}.jpg
 * New: uploads/images/product/{file}.jpg
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('products')
            || ! DB::getSchemaBuilder()->hasColumn('products', 'product_image')) {
            return;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory('uploads/images/product');

        $rows = DB::table('products')
            ->whereNotNull('product_image')
            ->where('product_image', '!=', '')
            ->get(['id', 'product_image']);

        foreach ($rows as $row) {
            $path = trim((string) $row->product_image);
            if ($path === '') {
                continue;
            }

            // Already under a module folder (uploads/images/{module}/file)
            if (preg_match('#^uploads/images/[^/]+/.+#', $path)) {
                continue;
            }

            // Only migrate flat uploads/images/{file} paths
            if (! preg_match('#^uploads/images/([^/]+)$#', $path, $m)) {
                continue;
            }

            $filename = $m[1];
            $newPath = 'uploads/images/product/' . $filename;

            if ($disk->exists($path)) {
                if (! $disk->exists($newPath)) {
                    $disk->move($path, $newPath);
                } else {
                    // Destination already exists — drop the old flat copy
                    $disk->delete($path);
                }
            }

            DB::table('products')->where('id', $row->id)->update([
                'product_image' => $newPath,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible: leave files under product/; do not flatten again.
    }
};
