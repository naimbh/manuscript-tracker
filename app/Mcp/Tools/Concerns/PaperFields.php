<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Paper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;

trait PaperFields
{
    /** Paper fields an agent may set, with their schema. */
    protected function paperSchema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string(),
            'short_title' => $schema->string()->description('A short name shown in lists, such as “Paper 1”.'),
            'stage' => $schema->string()->enum(array_keys(Paper::STAGES)),
            'started_on' => $schema->string()->description('YYYY-MM-DD, when work on the paper began. Defaults to today for a new paper.'),
            'abstract' => $schema->string(),
            'keywords' => $schema->string()->description('Separated by semicolons.'),
            'method' => $schema->string()->description('The specific analytic method, at most 255 characters.'),
            'dataset' => $schema->string()->description('The data the paper uses, at most 255 characters.'),
            'tags' => $schema->array()->items($schema->string())->description('For example ["Thesis", "Preprint"].'),
            'notes' => $schema->string(),
            'overleaf_url' => $schema->string(),
            'drive_url' => $schema->string(),
            'preprint_server' => $schema->string()->description('For example “arXiv (cs.CY)”.'),
            'preprint_url' => $schema->string(),
            'preprint_on' => $schema->string()->description('YYYY-MM-DD'),
        ];
    }

    /** The paper fields present in the request, trimmed to the column limits. */
    protected function paperData(Request $request): array
    {
        $d = [];
        foreach (['title', 'short_title', 'stage', 'started_on', 'abstract', 'keywords', 'method', 'dataset', 'tags', 'notes', 'overleaf_url', 'drive_url', 'preprint_server', 'preprint_url', 'preprint_on'] as $k) {
            if ($request->get($k) !== null) {
                $v = $request->get($k);
                $d[$k] = is_string($v) && in_array($k, ['method', 'dataset', 'short_title'], true) ? mb_substr($v, 0, 255) : $v;
            }
        }
        if (isset($d['stage']) && ! isset(Paper::STAGES[$d['stage']])) {
            unset($d['stage']);
        }

        return $d;
    }
}
