<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM Provider
    |--------------------------------------------------------------------------
    | Supported: "openai"
    | Designed for later extension: gemini, anthropic, local
    */
    'provider'           => env('AI_PROVIDER', 'openai'),
    'embedding_provider' => env('AI_EMBEDDING_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */
    'chat_model'       => env('AI_CHAT_MODEL', 'gpt-4o-mini'),
    'embedding_model'  => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    'embedding_dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | RAG / Retrieval Settings
    |--------------------------------------------------------------------------
    */
    'document_similarity_threshold' => (float) env('AI_DOCUMENT_SIMILARITY_THRESHOLD', 0.50),

    'max_chat_attachment_chunks' => (int) env('AI_MAX_CHAT_ATTACHMENT_CHUNKS', 6),
    'max_knowledge_chunks'       => (int) env('AI_MAX_KNOWLEDGE_CHUNKS', 8),
    'max_context_chunks'         => (int) env('AI_MAX_CONTEXT_CHUNKS', 12),

    'recent_messages_limit' => (int) env('AI_RECENT_MESSAGES_LIMIT', 12),
    'max_child_memories'    => (int) env('AI_MAX_CHILD_MEMORIES', 20),

    'max_chat_attachments_per_conversation' => (int) env('AI_MAX_CHAT_ATTACHMENTS_PER_CONVERSATION', 5),

    /*
    |--------------------------------------------------------------------------
    | Web Search Fallback
    |--------------------------------------------------------------------------
    */
    'web_search_enabled'  => (bool) env('AI_WEB_SEARCH_ENABLED', false),
    'web_search_provider' => env('AI_WEB_SEARCH_PROVIDER', 'brave'),

    /*
    |--------------------------------------------------------------------------
    | API Keys (resolved from env — never hardcoded)
    |--------------------------------------------------------------------------
    */
    'openai_api_key'    => env('OPENAI_API_KEY', env('AI_OPENAI_API_KEY')),
    'gemini_api_key'    => env('GEMINI_API_KEY'),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    'brave_api_key'     => env('BRAVE_SEARCH_API_KEY'),
    'serpapi_api_key'   => env('SERPAPI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    */
    'system_prompt' => <<<'PROMPT'
You are an assistant helping a parent, caregiver, teacher, or therapist understand information related to a child with special needs.

You may receive four types of context:

1. CHAT_ATTACHMENT sources: Files uploaded by the user in the current conversation. These are private and have highest priority.
2. CHILD_CONTEXT: The selected child's profile, memories, and previous conversation summary.
3. KNOWLEDGE_BASE sources: Internal system knowledge documents. These provide general guidance.
4. WEB sources: General web information, used only when enabled and when local sources are not enough.

Core rules:
1. Use chat attachments first when relevant.
2. Use child context when relevant.
3. Use knowledge base for general support.
4. Use web only when local sources are not enough.
5. Never use information from another child.
6. Never use files from another conversation.
7. Do not invent facts.
8. If the answer is not found in provided sources, say so clearly.
9. Do not diagnose medical, psychological, developmental, or educational conditions.
10. Do not prescribe medicine or treatment.
11. Do not replace a doctor, therapist, psychologist, teacher, or specialist.
12. For urgent medical, safety, or crisis situations, advise contacting local emergency services or a qualified professional.
13. Give clear, practical, supportive guidance.
14. Cite sources using source labels like [CHAT_SOURCE_1], [KB_SOURCE_2], or [WEB_SOURCE_1].
15. Do not create fake references.
16. If unsure, say you are unsure.
PROMPT,

];
