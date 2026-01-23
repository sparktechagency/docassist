<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

trait UploadFile
{
    public function uploadFile(UploadedFile $file, string $folder, ?string $oldFilePath = null):string
    {
        if ($oldFilePath && File::exists(public_path($oldFilePath))) {
            File::delete(public_path($oldFilePath));
        }

        $fullFolderPath = public_path($folder);
        if (!File::exists($fullFolderPath)) {
            File::makeDirectory($fullFolderPath, 0755, true);
        }

        $manager = new ImageManager(new Driver());

        $img = $manager->read($file);

        $quality = 85;
        $webp = $img->toWebp(quality: 85);
        while (strlen((string) $webp) > 100 * 1024 && $quality > 50) {
            $quality -= 5;
            $webp = $img->toWebp(quality: $quality);
        }

//        $fileName = time() . '.webp';
////        $fileName = $file->getClientOriginalName();
//
//        $file->move($fullFolderPath, $fileName);

        $fileName = Str::uuid()->toString() . '.webp';

        // 🔑 Save processed image, NOT original file
        file_put_contents($fullFolderPath . '/' . $fileName, (string) $webp);
//        dd($folder . $fileName);
        return $folder . $fileName;
    }
}
