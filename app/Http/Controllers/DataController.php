<?php

namespace App\Http\Controllers;

use App\Support\Backup;

class DataController extends Controller
{
    public function export()
    {
        return response(Backup::json(Backup::export(auth()->user())), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="manuscript-tracker-'.now()->format('Y-m-d').'.json"',
        ]);
    }

    public function exportZip()
    {
        return response()->download(Backup::zip(auth()->user()), 'manuscript-tracker-'.now()->format('Y-m-d').'.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }
}
