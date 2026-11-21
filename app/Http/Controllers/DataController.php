<?php

namespace App\Http\Controllers;

use App\Support\Backup;

class DataController extends Controller
{
    public function export()
    {
        $json = json_encode(Backup::export(auth()->user()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="manuscript-tracker-'.now()->format('Y-m-d').'.json"',
        ]);
    }
}
