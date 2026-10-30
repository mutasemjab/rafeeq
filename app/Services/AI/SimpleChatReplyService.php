<?php

namespace App\Services\AI;

class SimpleChatReplyService
{
    /** Exact standalone social messages only; mixed questions always use the full pipeline. */
    public function reply(string $message, string $language): ?string
    {
        $text = mb_strtolower(trim($message));
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = preg_replace('/[\p{P}\p{Z}\s]+/u', ' ', $text);
        $text = trim($text);
        $greetings = ['السلام عليكم', 'السلام عليكم ورحمة الله', 'السلام عليكم ورحمة الله وبركاته', 'وعليكم السلام', 'مرحبا', 'مرحباً', 'اهلا', 'أهلا', 'أهلا وسهلا', 'اهلا وسهلا', 'صباح الخير', 'مساء الخير', 'hi', 'hello', 'hey', 'good morning', 'good evening'];
        if (in_array($text, $greetings, true)) {
            return $language === 'ar'
                ? (str_contains($text, 'السلام') ? 'وعليكم السلام ورحمة الله! أهلاً بيك في رفيق. أقدر أساعدك في إيه؟' : 'أهلاً بيك في رفيق! أقدر أساعدك في إيه؟')
                : 'Hello! Welcome to Rafiq. How can I help?';
        }
        if (in_array($text, ['شكرا', 'شكراً', 'شكرا لك', 'شكرًا لك', 'متشكر', 'thanks', 'thank you'], true)) {
            return $language === 'ar' ? 'العفو، أنا معاك لو احتجت مساعدة تانية.' : 'You’re welcome. I’m here if you need more help.';
        }
        return null;
    }
}
