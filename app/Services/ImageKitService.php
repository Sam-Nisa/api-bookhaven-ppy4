<?php

namespace App\Services;

use ImageKit\ImageKit;

class ImageKitService
{
    protected $imageKit;

    public function __construct()
    {
        $this->imageKit = new ImageKit(
            config('services.imagekit.public_key'),
            config('services.imagekit.private_key'),
            config('services.imagekit.url_endpoint')
        );
    }

    public function upload($file, $fileName, $folder = '/uploads')
    {
        return $this->imageKit->upload([
            'file' => fopen($file, 'r'),
            'fileName' => $fileName,
            'folder' => $folder,
        ]);
    }
}
