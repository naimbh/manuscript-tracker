<?php

namespace App\Http\Controllers;

use App\Models\PaperFile;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    private function authorizeFile(PaperFile $file): void
    {
        abort_unless($file->paper->user_id === auth()->id(), 404);
    }

    public function show(PaperFile $file)
    {
        $this->authorizeFile($file);

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($file->original_name).'"',
        ]);
    }

    public function download(PaperFile $file)
    {
        $this->authorizeFile($file);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
