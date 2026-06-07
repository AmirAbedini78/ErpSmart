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
use ZipArchive;
use Illuminate\Support\Facades\Storage;
use Modules\Saas\Models\Template;
use Illuminate\Support\Facades\File;

class TemplateController extends Controller
{

    public function upload(Request $request, $id)
    {
        $request->validate([
            'template' => 'required|file|mimes:zip',
        ]);

        $page = Page::findOrFail($id);
        $zipFile = $request->file('template');
        $zipName = pathinfo($zipFile->getClientOriginalName(), PATHINFO_FILENAME);
        $uuid = \Str::uuid();
        $extractPath = "public/templates/{$uuid}"; // Store in public
        $zipPath = $zipFile->storeAs('temp', "{$uuid}.zip");

        $zip = new ZipArchive;
        if ($zip->open(storage_path("app/{$zipPath}")) === true) {
            File::makeDirectory(storage_path("app/public/{$zipPath}"), 0755, true);
            $zip->extractTo(storage_path("app/{$extractPath}"));
            $zip->close();
            Storage::delete($zipPath);
        } else {
            return response()->json(['error' => 'Failed to extract ZIP file'], 500);
        }

        $indexFilePath = storage_path("app/{$extractPath}/index.html");

        if (file_exists($indexFilePath)) {
            $indexContent = file_get_contents($indexFilePath);
            $updatedContent = preg_replace_callback(
                '/<(img|link|script)[^>]+(src|href)=\"([^\"]+)\"/',
                function ($matches) use ($extractPath) {
                    if (!isset($matches[3])) {
                        return $matches[0];
                    }
                    $tag = $matches[1];
                    $attr = $matches[2];
                    $assetPath = $matches[3];

                    if (strpos($assetPath, '{{ $SAAS_LOGO }}') !== false) {
                        return $matches[0];
                    }

                    if (!preg_match('/^https?:\/\//', $assetPath)) {
                        $newAssetPath = asset(Storage::url("{$extractPath}/{$assetPath}"));
                        if ($tag == 'link' && $attr == 'href') {
                            return "<{$tag} {$attr}=\"{$newAssetPath}\" rel=\"stylesheet\"";
                        }
                        return "<{$tag} {$attr}=\"{$newAssetPath}\"";
                    }
                    return $matches[0];
                },
                $indexContent
            );


            file_put_contents($indexFilePath, $updatedContent);

            $template = Template::create([
                'name' => $zipName,
                'path' => str_replace('public/', '', $extractPath),
                'index_file' => 'index.html',
            ]);
            $page->update([
                'name' => $page->name,
                'html' => $updatedContent,
                'template_id' => $template->id,
                'css'  => '',
                'status' => 'archived',
            ]);

            return response()->json(['template' => $template]);
        }
    }


    public function list()
    {
        return response()->json(Template::all());
    }

    public function getTemplate($uuid)
    {
        $template = Template::where('uuid', $uuid)->firstOrFail();
        $indexContent = Storage::get("{$template->path}/{$template->index_file}");

        return response()->json([
            'html' => $indexContent,
            'assets' => url("storage/{$template->path}"),
        ]);
    }
    public function download()
    {

        $filePath = base_path('modules/Saas/public/sample.zip');


        if (file_exists($filePath)) {

            return response()->download($filePath);
        } else {

            return response()->json(['error' => 'File not found.'], 404);
        }
    }
}
