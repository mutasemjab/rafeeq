<?php

namespace App\Services\Search;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** The same approval and age boundaries apply to every knowledge search path. */
class KnowledgeSourceQuery
{
    public static function make(array $filters = []): Builder
    {
        return DB::table('knowledge_document_chunks as c')
            ->join('knowledge_documents as d', 'd.id', '=', 'c.knowledge_document_id')
            ->where('d.status', 'processed')
            ->whereNull('d.deleted_at')
            ->when(($filters['approved_only'] ?? true) === true, fn ($query) => $query->where('d.is_approved', true))
            ->when(($filters['require_age_scope'] ?? false) === true, function ($query) use ($filters) {
                return $query->where(function ($scope) use ($filters) {
                    $scope->where(fn ($q) => $q->where('d.audience', 'all_ages')->whereNull('d.age_min_months')->whereNull('d.age_max_months'));
                    if (isset($filters['age_months'])) {
                        $scope->orWhere(fn ($q) => $q->whereNotNull('d.age_min_months')->orWhereNotNull('d.age_max_months'));
                    }
                });
            })
            ->when(isset($filters['age_months']), function ($query) use ($filters) {
                $age = max(0, (int) $filters['age_months']);
                return $query->where(fn ($q) => $q->whereNull('d.age_min_months')->orWhere('d.age_min_months', '<=', $age))
                    ->where(fn ($q) => $q->whereNull('d.age_max_months')->orWhere('d.age_max_months', '>=', $age));
            });
    }
}
