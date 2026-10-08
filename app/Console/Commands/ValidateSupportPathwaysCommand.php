<?php

namespace App\Console\Commands;

use App\Services\AI\SupportPathwayRegistry;
use Illuminate\Console\Command;

class ValidateSupportPathwaysCommand extends Command
{
    protected $signature = 'support:validate {--json : Emit the review inventory as JSON}';

    protected $description = 'Validate bilingual draft pathways and report review gaps without making model calls';

    public function handle(SupportPathwayRegistry $registry): int
    {
        $package = $registry->package();
        $result = [
            'version' => $package['version'], 'supplied_pathways' => count($package['pathways']),
            'additional_intake_pathways' => count($registry->pathways()) - count($package['pathways']),
            'specialised_question_occurrences' => collect($package['pathways'])->sum(fn ($pathway): int => collect($pathway['nodes'])->filter(fn ($node): bool => str_starts_with($node['local_id'], 'D'))->count()),
            'clinical_evidence' => false,
            'release_ready' => false,
            'pending' => ['clinical_pathway_review', 'age_matched_evidence_coverage', 'clinical_examples_review', 'mobile_person_profile_ui', 'ephemeral_conversations'],
            'pathways' => $registry->catalogue(),
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Inventory', 'Count'], [
                ['Supplied bilingual draft pathways', $result['supplied_pathways']],
                ['Authored gap intake pathways', $result['additional_intake_pathways']],
                ['Supplied specialised question occurrences', $result['specialised_question_occurrences']],
            ]);
            $this->warn('Draft question registry validated. Clinical review and release work remain incomplete.');
        }

        return self::SUCCESS;
    }
}
