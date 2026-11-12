<?php

namespace App\Services\AI;

/** Align the first draft with the existing independent review's evidence rules. */
class ReferenceDraftGuidance
{
    /** Routing hint only: never used to decide coverage or reduce retrieval. */
    public static function structuredProtocol(array $sources): bool
    {
        foreach ($sources as $source) {
            $text = (string) ($source['content'] ?? '');
            $prerequisite = preg_match('/\bpre[-\s]?requisites?\b|(?:شروط|مهارات|متطلبات)\s+(?:مسبقة|سابقة)/iu', $text);
            $sequence = preg_match('/\btarget\s*\d+|\btarget\s+criteria\b|\btask\s+analysis\b|تحليل\s+المهمة|(?:الهدف|هدف|الخطوة|خطوة)\s*\d+/iu', $text);
            if ($prerequisite && $sequence) return true;
        }
        return false;
    }

    public static function instruction(): string
    {
        return <<<'PROMPT'

REFERENCE-FAITHFUL FIRST DRAFT: Before writing, reconcile the latest request, reported abilities, and the actual passage. A program's target/outcome, heading or parenthetical description is not a prerequisite. Only an explicit prerequisite in the passage may be treated as one; do not invent readiness gates or delays.
If the source explicitly allows a step to be skipped when a reported ability is present, build on that ability and move to the next supported useful step. Do not re-teach a mastered step, offer generic familiarization in its place, or default to starting the whole program. Preserve any genuinely unmet prerequisites and the source's applicability limits. An ability reported for one task is not proof of mastery in every setting.
For a practical request, give one source-supported priority that addresses the user's CURRENT goal or barrier. Tie it briefly to the known facts. Identify ONE concrete observation, rather than general monitoring, a multi-field checklist, or an invented deadline/target. State the observation as a short declarative sentence, not a question. Do not invent a rule linking that observation to a later intervention or training stage; such a decision rule also requires explicit source support. Use only the supplied evidence and cite it. A bounded starting step is not a complete individualized protocol. Do this check silently; do not expose a checklist or add a closing follow-up question.
PROMPT;
    }
}
