<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ImageKitService;

class UploadController extends Controller
{
    protected $imageKit;

    public function __construct(ImageKitService $imageKit)
    {
        $this->imageKit = $imageKit;
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240' // 10MB
        ]);

        $file = $request->file('file');

        $upload = $this->imageKit->upload(
            $file->getPathname(),
            time() . '_' . $file->getClientOriginalName(),
            '/uploads'
        );

        return response()->json([
            'url' => $upload->result->url,
            'fileId' => $upload->result->fileId
        ]);
    }
}
