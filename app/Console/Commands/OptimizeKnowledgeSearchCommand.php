<?php

namespace App\Console\Commands;

use App\Services\Search\PackedEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeKnowledgeSearchCommand extends Command
{
    protected $signature = 'knowledge:optimize-search {--force : Rebuild already packed vectors}';
    protected $description = 'Pack existing knowledge vectors for faster search without API calls or changing source text';

    public function handle(): int
    {
        $count = 0;
        $invalid = 0;
        DB::table('knowledge_document_chunks')->when(! $this->option('force'), fn ($q) => $q->whereNull('embedding_vector'))
            ->select(['id', 'embedding', 'embedding_dimensions'])->chunkById(100, function ($rows) use (&$count, &$invalid): void {
                foreach ($rows as $row) {
                    $binary = PackedEmbedding::encode($row->embedding, (int) $row->embedding_dimensions);
                    if ($binary === null) { $invalid++; continue; }
                    // Do not overwrite a concurrent re-embedding job's vector.
                    $count += DB::table('knowledge_document_chunks')->where('id', $row->id)
                        ->where('embedding', $row->embedding)->where('embedding_dimensions', $row->embedding_dimensions)
                        ->update(['embedding_vector' => $binary]);
                }
            });
        $this->info("Packed {$count} vectors; invalid vectors skipped: {$invalid}. No API calls.");
        return $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }
}
