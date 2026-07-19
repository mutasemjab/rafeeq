<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentChunk;
use Illuminate\Console\Command;

class KnowledgeStatusCommand extends Command
{
    protected $signature = 'knowledge:status
        {--category= : Limit status to one category}
        {--json : Return machine-readable JSON}';

    protected $description = 'Show knowledge ingestion progress and recent failures';

    public function handle(): int
    {
        $query = KnowledgeDocument::query()
            ->when($this->option('category'), fn($builder, $category) =>
                $builder->where('category', $category)
            );
        $ids = (clone $query)->pluck('id');
        $payload = [
            'documents' => [
                'total' => (clone $query)->count(),
                'uploaded' => (clone $query)->where('status', 'uploaded')->count(),
                'processing' => (clone $query)->where('status', 'processing')->count(),
                'processed' => (clone $query)->where('status', 'processed')->count(),
                'failed' => (clone $query)->where('status', 'failed')->count(),
            ],
            'chunks' => KnowledgeDocumentChunk::query()->whereIn('knowledge_document_id', $ids)->count(),
            'recent_failures' => (clone $query)
                ->where('status', 'failed')
                ->latest('updated_at')
                ->limit(10)
                ->get(['id', 'original_name', 'processing_error'])
                ->toArray(),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->table(['Status', 'Documents'], collect($payload['documents'])
            ->map(fn($count, $status): array => [$status, $count])
            ->values()
            ->all());
        $this->info('Embedded chunks: '.$payload['chunks']);

        if ($payload['recent_failures'] !== []) {
            $this->newLine();
            $this->warn('Recent failures:');
            $this->table(['ID', 'File', 'Error'], array_map(fn(array $failure): array => [
                $failure['id'],
                $failure['original_name'],
                mb_substr((string) $failure['processing_error'], 0, 180),
            ], $payload['recent_failures']));
        }

        return self::SUCCESS;
    }
}
