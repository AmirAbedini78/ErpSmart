<?php

namespace Modules\Saas\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Installer\DatabaseTest;
use Modules\Installer\PrivilegesChecker;
use Modules\Saas\Models\Page;
use Symfony\Component\HttpFoundation\Response;


class ImageController extends Controller
{
    /**
     * Handle image upload.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'nullable',
            'image.*' => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $urls = [];

        foreach ($request->file('image') as $image) {
            $path = $image->store('images', 'public');
            $urls[] = asset('storage/' . $path);
        }

        return response()->json(['data' => $urls]);
    }
}
