<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Serves the signed-in user's profile photo from private storage. */
class ProfilePhotoController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, basename($path), [
            'Content-Type' => str_ends_with($path, '.webp') ? 'image/webp' : (str_ends_with($path, '.jpg') ? 'image/jpeg' : 'image/png'),
            'Content-Disposition' => 'inline',
            // The URL carries a version (?v=...), so the browser may keep it.
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
