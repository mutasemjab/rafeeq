<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AiCostReportCommand extends Command
{
    protected $signature = 'ai:cost-report {--days=7} {--by=question : question, stage, or day} {--include-evaluations} {--json}';
    protected $description = 'Report estimated AI cost, including all stages, retries and missing usage (no question text).';

    public function handle(): int
    {
        if (!in_array($this->option('by'), ['question', 'stage', 'day'], true)) {
            $this->error('--by must be question, stage, or day.'); return 1;
        }
        $query = DB::table('ai_request_usages')->where('created_at', '>=', now()->subDays(max(1, min(365, (int) $this->option('days')))));
        if (!$this->option('include-evaluations')) $query->where('purpose', '!=', 'evaluation');
        $group = match ($this->option('by')) {
            'stage' => ['stage', 'model', 'variant'],
            'day' => [DB::raw('DATE(created_at) as day'), 'variant'],
            default => ['question_key', 'user_message_id', 'variant'],
        };
        $groups = array_map(fn ($field) => is_string($field) ? $field : DB::raw('DATE(created_at)'), $group);
        $rows = $query->select($group)->selectRaw("SUM(CASE WHEN operation != 'local_reply' THEN 1 ELSE 0 END) requests, SUM(CASE WHEN operation != 'local_reply' AND succeeded = 0 THEN 1 ELSE 0 END) failed_requests, SUM(CASE WHEN estimated_cost_usd IS NULL THEN 1 ELSE 0 END) unpriced_requests, SUM(input_tokens) input_tokens, SUM(output_tokens) output_tokens, SUM(cached_input_tokens) cached_input_tokens, SUM(cache_write_tokens) cache_write_tokens, SUM(web_search_calls) web_search_calls, SUM(estimated_cost_usd) known_estimated_cost_usd")->groupBy($groups)->orderByDesc('known_estimated_cost_usd')->get();
        if ($this->option('json')) {
            $this->line(json_encode(['currency' => 'USD', 'note' => 'Estimates, not invoices. If unpriced_requests > 0, the known subtotal is incomplete. Evaluations are excluded unless requested. Historical costs before metering are unavailable.', 'rows' => $rows], JSON_PRETTY_PRINT));
        } else {
            $this->warn('USD estimates, not invoices. Unknown request costs are NOT zero. Historical costs before metering are unavailable.');
            $data = $rows->map(fn ($row) => (array) $row)->all();
            if ($data) $this->table(array_keys($data[0]), $data);
            else $this->info('No metered requests in this period.');
        }
        return 0;
    }
}
