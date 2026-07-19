<?php

namespace App\Modules\Api\V1\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function allowedModules(Request $request)
    {
        $modules = $request->user()->getAllowedModules();

        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }
}
