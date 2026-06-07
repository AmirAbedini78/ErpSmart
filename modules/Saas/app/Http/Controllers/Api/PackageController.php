<?php

namespace Modules\Saas\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Modules\Saas\Models\Package;

class PackageController extends Controller
{
    public function __invoke($id)
    {
        $package = Package::with('quotas')->find($id);

        foreach ($package->quotas as $quota)
        {
            $name = "limit_{$quota->id}";
            $package->$name = $quota->pivot->limit;
        }

        return response()->json($package);
    }

}
