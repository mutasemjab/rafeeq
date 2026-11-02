<?php

namespace App\Services\AI;

final class ArabicReplyStyle
{
    public const REGISTERS = ['levantine', 'egyptian', 'gulf', 'iraqi', 'maghrebi', 'colloquial_other', 'neutral'];

    public static function instruction(string $register): string
    {
        $style = match ($register) {
            'levantine' => 'اكتب الرد كله بشامية محكية خفيفة: مثلاً «يعني إنّه…»، «هالشي ممكن يأثّر…»، «مو بالضرورة…».',
            'egyptian' => 'اكتب الرد كله بمصري محكي خفيف: مثلاً «ده معناه إن…»، «ممكن يخلّي…»، «مش شرط…».',
            'gulf' => 'اكتب بأسلوب خليجي محكي خفيف وقريب من مفردات السائل.',
            'iraqi' => 'اكتب بأسلوب عراقي محكي خفيف وقريب من مفردات السائل.',
            'maghrebi' => 'اكتب بالدارجة القريبة من مفردات السائل، من غير افتراض بلده.',
            'colloquial_other' => 'اكتب بالمحكية القريبة من مفردات السائل.',
            default => 'اكتب بعربية واضحة وبسيطة؛ احتفظ بأي اختيار صريح للسائل بشأن أسلوب الرد.',
        };
        return $style.' حافظ على دقة المصطلحات والمراجع. اشرح بكلامك، ولا تنقل أسلوب الكتاب الرسمي. الأمثلة للأسلوب فقط؛ لا تضف تحية أو تفترض هوية السائل.';
    }
}
