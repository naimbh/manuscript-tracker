<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesUser;
use App\Models\PaperFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach a file from this computer (manuscript, anonymized manuscript, cover letter, response to reviewers, proof PDF or supplementary file) to a paper, optionally to one submission. Works only with the local server, because it reads a local path.')]
class AttachFile extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        if ($request->user()) {
            return Response::error('Files can only be attached through the local server. Upload it in the app instead.');
        }
        $p = $this->owner($request)->papers()->find($request->get('paper_id'));
        if (! $p) {
            return Response::error('No paper with that id.');
        }
        $src = (string) $request->get('path');
        if (! is_file($src) || ! is_readable($src)) {
            return Response::error('File not found: '.$src);
        }
        if (filesize($src) > 50 * 1048576) {
            return Response::error('The file is larger than 50 MB.');
        }
        $kind = $request->get('kind', 'manuscript');
        if (! isset(PaperFile::KINDS[$kind])) {
            return Response::error('Unknown kind.');
        }
        $subId = $request->get('submission_id');
        if ($subId && ! $p->submissions()->whereKey($subId)->exists()) {
            return Response::error('That submission does not belong to this paper.');
        }
        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
        $path = 'papers/'.$p->id.'/'.Str::random(40).($ext ? '.'.$ext : '');
        Storage::disk('local')->put($path, file_get_contents($src));
        $f = $p->files()->create([
            'submission_id' => $subId, 'kind' => $kind, 'label' => $request->get('label'), 'path' => $path,
            'original_name' => $request->get('name') ?: basename($src), 'mime' => mime_content_type($src) ?: null, 'size' => filesize($src),
        ]);

        return Response::text("Attached {$f->original_name} ({$f->humanSize()}) to “{$p->title}” as ".PaperFile::KINDS[$kind].'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'paper_id' => $schema->integer()->required(),
            'path' => $schema->string()->description('Absolute path to the file on this computer.')->required(),
            'kind' => $schema->string()->enum(array_keys(PaperFile::KINDS)),
            'label' => $schema->string()->description('A short label, such as “ITD submission PDF”.'),
            'name' => $schema->string()->description('File name to show. Defaults to the file’s own name.'),
            'submission_id' => $schema->integer(),
        ];
    }
}
