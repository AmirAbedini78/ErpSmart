<?php

namespace Modules\Saas\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class GenerateTranslationController extends Controller
{
    /**
     * Get the deals initial board data.
     */
    public function generateTranslations(Request $request)
    {
        Artisan::call('translator:json');

        return response()->json([
            'message' => 'Translations generated successfully',
        ]);
    }

}
