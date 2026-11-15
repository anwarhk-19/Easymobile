<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FileStore
{
    /** Public images (product photos, logos). Files get random names so uploads can't be guessed or overwrite each other. */
    public function storePublic(UploadedFile $file, string $dir): string
    {
        return $file->storeAs($dir, Str::uuid().'.'.$file->extension(), 'public');
    }

    /** Private files (verification documents, evidence, repair photos) — never web-accessible directly. */
    public function storePrivate(UploadedFile $file, string $dir): string
    {
        return $file->storeAs($dir, Str::uuid().'.'.$file->extension(), 'local');
    }
}
