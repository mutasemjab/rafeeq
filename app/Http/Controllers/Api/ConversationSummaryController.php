<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AI\ConversationGuidanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationSummaryController extends Controller
{
    /** On-demand, local composition from owned records; no model or external sharing. */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $request->validate(['language' => 'nullable|in:ar,en']);
        $ar = $request->input('language', $request->user()->preferred_language ?? 'ar') === 'ar';
        $state = (array) $conversation->case_state;
        $messages = $conversation->messages()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(100)->get()->keyBy('id');
        $reports = [];
        $goal = null;
        foreach ((array) ($state['fact_index'] ?? []) as $key => $fact) {
            $source = $messages->get($fact['source_message_id'] ?? 0);
            $quote = trim((string) ($fact['evidence'] ?? ''));
            if ($source?->role === 'user' && $quote !== '' && mb_stripos($source->content, $quote) !== false) {
                $reports[] = $source->created_at->toDateString().' — '.mb_substr($quote, 0, 600);
                if ($key === 'goal.current') $goal = mb_substr($quote, 0, 600);
            }
        }
        if ($reports === []) {
            $reports = $messages->filter(fn ($m) => $m->role === 'user' && ! data_get($m->metadata, 'feedback')
                && ! in_array(data_get($m->metadata, 'delivery_status'), ['queued', 'processing', 'failed', 'cancelled'], true))
                ->take(10)->reverse()->map(fn ($m) => $m->created_at->toDateString().' — '.mb_substr($m->content, 0, 600))->values()->all();
        }
        $outcomes = [];
        foreach ((array) data_get($state, 'progress.outcome_reports', []) as $outcome) {
            $source = $messages->get($outcome['source_message_id'] ?? 0);
            if ($source?->role === 'user') $outcomes[] = $source->created_at->toDateString().' — '.mb_substr($source->content, 0, 600);
        }
        $attemptSource = $messages->get(data_get($state, 'progress.attempt_source_message_id'));
        if ($attemptSource?->role === 'user') $outcomes[] = $attemptSource->created_at->toDateString().' — '.mb_substr($attemptSource->content, 0, 600);
        $labels = collect(app(ConversationGuidanceService::class)->fields())->mapWithKeys(fn ($f) => [$f['key'] => $f['label'][$ar ? 'ar' : 'en']])->all();
        $unknowns = array_map(fn ($field) => $labels[$field] ?? str_replace('_', ' ', $field), (array) ($state['missing_fields'] ?? []));
        $title = $ar ? 'ملخص المحادثة للمراجعة' : 'Conversation summary for review';
        $empty = $ar ? 'لم تُسجل معلومات كافية في هذه المحادثة.' : 'Not enough information was recorded in this conversation.';
        $lines = [$title, now()->toDateString(), '',
            $ar ? 'مسودة من سجل هذه المحادثة، وليست تقريرًا تشخيصيًا. راجع النص والأسماء وأي بيانات تعريفية قبل المشاركة.' : 'Draft from this conversation, not a diagnostic report. Review the text, names, and identifying details before sharing.', '',
            $ar ? 'ما ذكرته الأسرة (مع تاريخ الرسالة)' : 'Family reports (message date)',
            ...($reports ?: [$empty]), '',
            $ar ? 'هدف الأسرة المبلغ عنه' : 'Reported family goal',
            $goal ?: ($ar ? 'لم يُسجل هدف صريح.' : 'No explicit goal was recorded.'), '',
            $ar ? 'اقتراح المساعد الأخير (لا يثبت أنه طُبّق)' : 'Latest assistant suggestion (does not establish that it was tried)',
            mb_substr((string) data_get($state, 'progress.recommended_step', $empty), 0, 2200), '',
            $ar ? 'ما جُرّب والنتائج كما أبلغت عنها الأسرة' : 'Attempts and outcomes reported by the family',
            ...($outcomes ?: [$ar ? 'لم تُسجل نتيجة مُبلّغ عنها؛ لا نفترض تطبيق الخطوة أو نجاحها.' : 'No outcome was reported; implementation or success is not assumed.']), '',
            $ar ? 'معلومات ما زالت غير معروفة' : 'Information still unknown',
            ...($unknowns ?: [$ar ? 'لم تُحدد فجوات إضافية في سجل المحادثة؛ هذا لا يعني اكتمال التقييم.' : 'No additional gaps were recorded; this does not establish a complete assessment.']), '',
            $ar ? 'سؤال مفتوح يمكن مناقشته مع المختص' : 'Open question to discuss with the specialist',
            $conversation->next_question ?: ($ar ? 'يمكن إضافة سؤال هنا قبل المشاركة.' : 'You can add a question here before sharing.'),
        ];
        return response()->json(['title' => $title, 'content' => implode("\n", $lines), 'source_message_count' => $messages->count(),
            'adds_profile_identity' => false, 'review_required' => true, 'shared' => false]);
    }
}
