#!/usr/bin/env python3
"""Import the supplied dialogue specifications as versioned reference data.

Usage: python3 scripts/import-conversation-guidance.py DOWNLOADS OUTPUT_DIRECTORY
No archive code is executed. The case form is a question catalogue, not evidence
that any child has a condition. Sensitive family history is optional.
"""
import hashlib
import json
import sys
import zipfile
from pathlib import Path

source, destination = map(Path, sys.argv[1:])
destination.mkdir(parents=True, exist_ok=True)
archives = {
    'قواعد اساسيه بالعربي.zip': 'dialogue_tree.json',
    'قواعد اساسيه بالانجليزي.zip': 'dialogue_tree_en.json',
    'قواعد تحسين المحادثه.zip': 'conversation_enhancements_ar_en.json',
    'Gateway_Additions_AR_EN (1).zip': 'gateway_additions_ar_en.json',
    'Rafiq_Product_Features_AR_EN.zip': 'product_features_ar_en.json',
}
manifest = {'version': '2026-10-22.1', 'clinical_evidence': False, 'sources': []}
for archive_name, entry in archives.items():
    raw = (source / archive_name).read_bytes()
    with zipfile.ZipFile(source / archive_name) as archive:
        data = archive.read(entry)
    json.loads(data)
    (destination / entry).write_bytes(data)
    manifest['sources'].append({'archive': archive_name, 'file': entry,
        'sha256': hashlib.sha256(data).hexdigest(), 'archive_sha256': hashlib.sha256(raw).hexdigest()})

ar = json.loads((destination / 'dialogue_tree.json').read_text())
en = json.loads((destination / 'dialogue_tree_en.json').read_text())
assert [n['id'] for n in ar['nodes']] == [n['id'] for n in en['nodes']]
for a, b in zip(ar['nodes'], en['nodes']):
    assert [t['target'] for t in a['transitions']] == [t['target'] for t in b['transitions']]

# Adapted, atomic questions from the supplied blank case-history form. Keys
# intentionally reuse existing memory keys; translations are authored here.
rows = '''child.age|general|family|العمر|Age|كم عمر الطفل؟|How old is the child?
child.birth_date|general|family|تاريخ الميلاد|Birth date|ما تاريخ ميلاد الطفل؟|What is the child's birth date?
family.siblings|family|family|الإخوة|Siblings|كم عدد إخوة الطفل؟|How many siblings does the child have?
family.household|family|family|المقيمون في المنزل|Household|من يعيش مع الطفل في المنزل؟|Who lives with the child at home?
family.parent_relationship|family|family|قرابة الوالدين|Parents' relationship|هل توجد صلة قرابة بين الوالدين؟|Are the parents related?
family.resources|family|family|موارد الأسرة|Family resources|ما الموارد المتاحة لتنفيذ الخطوة؟|What resources are available for this step?
health.pregnancy_history|development|pregnancy|تاريخ الحمل|Pregnancy history|هل توجد معلومة عن الحمل ترغب الأسرة في ذكرها؟|Is there any pregnancy history the family wishes to share?
health.gestational_age|development|birth|مدة الحمل|Gestational age|ما مدة الحمل عند الولادة؟|What was the gestational age at birth?
health.birth_history|development|birth|تاريخ الولادة|Birth history|هل احتاج الطفل إلى مساعدة طبية عند الولادة؟|Did the child need medical support at birth?
health.birth_weight|development|birth|وزن الولادة|Birth weight|كم كان وزن الطفل عند الولادة؟|What was the child's birth weight?
health.neonatal_care|development|birth|رعاية حديثي الولادة|Neonatal care|هل احتاج الطفل إلى رعاية حديثي الولادة؟|Did the child need neonatal care?
health.hearing_assessment|communication|assessments|فحص السمع|Hearing assessment|هل أُجري فحص سمع للطفل؟|Has the child had a hearing assessment?
health.vision_assessment|learning movement|assessments|فحص البصر|Vision assessment|هل أُجري فحص بصر للطفل؟|Has the child had a vision assessment?
health.other_assessments|development|assessments|فحوصات سابقة|Previous assessments|ما الفحوصات السابقة ذات الصلة بالمشكلة الحالية؟|Which previous assessments relate to the current concern?
health.assessment_results|development|assessments|نتائج الفحوصات|Assessment results|ما النتيجة المذكورة في الفحص؟|What result was reported in the assessment?
health.ear_history|communication|health|تاريخ الأذن|Ear history|هل سبق الإبلاغ عن مشكلة في الأذن؟|Has an ear problem been reported before?
health.eye_history|learning movement|health|تاريخ العين|Eye history|هل سبق الإبلاغ عن مشكلة في العين؟|Has an eye problem been reported before?
health.head_injury_history|development movement behavior|health|سقوط أو إصابة الرأس|Fall or head injury|هل سبق أن تعرض الطفل لسقوط على الرأس؟|Has the child previously fallen on their head?
health.accident_history|development movement|health|الحوادث السابقة|Previous accidents|هل تعرض الطفل لحادث سابق ذي صلة بما وصفته؟|Has there been a previous accident relevant to what you described?
health.seizure_history|development|health|تشنجات مبلغ عنها|Reported seizures|هل سبق الإبلاغ عن نوبات تشنج؟|Have seizures been reported before?
health.medical_history|development|health|التاريخ الصحي|Health history|هل توجد حالة صحية مبلغ عنها تؤثر في النشاط الحالي؟|Is there a reported health condition affecting this activity?
health.medications|development|health|الأدوية المبلغ عنها|Reported medicines|ما الأدوية المستخدمة حاليًا بحسب الأسرة؟|Which medicines does the family report are currently used?
health.allergies|routine|health|الحساسية المبلغ عنها|Reported allergies|هل توجد حساسية معروفة بحسب الأسرة؟|Does the family report any known allergy?
development.sitting|movement|milestones|الجلوس|Sitting|في أي عمر بدأ الطفل الجلوس دون مساعدة؟|At what age did the child sit without support?
development.walking|movement|milestones|المشي|Walking|في أي عمر بدأ الطفل المشي؟|At what age did the child start walking?
development.fine_motor|movement learning|milestones|الحركة الدقيقة|Fine motor skills|كيف يستخدم الطفل يديه في المهمة التي وصفتها؟|How does the child use their hands in the task you described?
development.gross_motor|movement|milestones|الحركة الكبيرة|Gross motor skills|ما الحركة التي يجد الطفل صعوبة فيها؟|Which movement does the child find difficult?
development.first_word|communication|language|أول كلمة|First word|في أي عمر نطق الطفل أول كلمة؟|At what age did the child say their first word?
development.first_sentence|communication|language|أول جملة|First sentence|في أي عمر بدأ الطفل استخدام جمل؟|At what age did the child start using sentences?
development.regression|communication development|language|فقدان مهارة مكتسبة|Loss of a previously used skill|هل توقف الطفل عن مهارة كان يستخدمها من قبل؟|Has the child stopped using a skill they previously had?
communication.comprehension|communication|language|الفهم|Understanding|ما مثال لطلب يفهمه الطفل؟|What is an example of a request the child understands?
communication.expression|communication|language|التعبير|Expression|كيف يطلب الطفل ما يحتاجه؟|How does the child ask for what they need?
communication.method|communication|language|وسيلة التواصل|Communication method|ما وسيلة التواصل التي يستخدمها الطفل؟|Which communication method does the child use?
communication.phrase_length|communication|language|طول الجملة|Phrase length|ما مثال لجملة يستخدمها الطفل؟|What is an example of a phrase the child uses?
communication.settings|communication social|language|سياقات الكلام|Communication settings|مع من يتواصل الطفل بسهولة؟|With whom does the child communicate comfortably?
communication.primary_language|communication|language|اللغة المستخدمة|Language used|ما اللغة المستخدمة مع الطفل في المنزل؟|Which language is used with the child at home?
communication.speech_clarity|communication|language|وضوح النطق|Speech clarity|ما الأصوات التي يجد الطفل صعوبة في نطقها؟|Which speech sounds does the child find difficult?
therapy.speech_support|communication|language|دعم النطق الحالي|Current speech support|هل يتلقى الطفل دعمًا للنطق حاليًا؟|Is the child currently receiving speech support?
social.play_preferences|social|social|اللعب المفضل|Play preferences|ما نوع اللعب الذي يفضله الطفل؟|What kind of play does the child prefer?
social.peer_interaction|social|social|التفاعل مع الأقران|Peer interaction|كيف يتفاعل الطفل مع أقرانه في موقف محدد؟|How does the child interact with peers in a specific situation?
social.family_interaction|social|social|التفاعل مع الأسرة|Family interaction|كيف يتفاعل الطفل مع الأسرة في النشاط الذي وصفته؟|How does the child interact with family during the activity you described?
behavior.onset|behavior|behavior|بداية السلوك|Behavior onset|متى بدأ السلوك الذي وصفته؟|When did the behavior you described begin?
behavior.context|behavior|behavior|سياق السلوك|Behavior context|في أي موقف يحدث السلوك؟|In which situation does the behavior happen?
behavior.antecedent|behavior|behavior|ما قبل السلوك|Before the behavior|ما الذي حدث قبل السلوك مباشرة؟|What happened immediately before the behavior?
behavior.consequence|behavior|behavior|ما بعد السلوك|After the behavior|ما الذي حدث بعد السلوك مباشرة؟|What happened immediately after the behavior?
behavior.frequency|behavior|behavior|تكرار السلوك|Behavior frequency|كم مرة تلاحظ السلوك؟|How often do you observe the behavior?
routine.sleep|routine|behavior|النوم|Sleep|ما التغير الذي لاحظته في النوم؟|What change have you noticed in sleep?
routine.eating|routine|behavior|الأكل|Eating|ما الصعوبة التي تلاحظها وقت الأكل؟|What difficulty do you notice at mealtimes?
routine.toileting|routine|milestones|استخدام الحمام|Toileting|ما المساعدة التي يحتاجها الطفل في استخدام الحمام؟|What help does the child need with toileting?
independence.dressing|movement routine|milestones|ارتداء الملابس|Dressing|ما الخطوة التي ينفذها الطفل بنفسه عند ارتداء الملابس؟|Which dressing step can the child do independently?
independence.eating|movement routine|milestones|الأكل باستقلالية|Independent eating|ما المساعدة التي يحتاجها الطفل ليأكل بنفسه؟|What help does the child need to eat independently?
learning.school_stage|learning|academic|المرحلة الدراسية|School stage|ما المرحلة الدراسية الحالية؟|What is the child's current school stage?
learning.reading|learning|academic|القراءة|Reading|ما مثال لمهمة قراءة يستطيع الطفل أداءها؟|What is an example of a reading task the child can do?
learning.writing|learning|academic|الكتابة|Writing|ما مثال لمهمة كتابة يستطيع الطفل أداءها؟|What is an example of a writing task the child can do?
learning.math|learning|academic|الحساب|Math|ما مثال لمهمة حساب يستطيع الطفل أداءها؟|What is an example of a math task the child can do?
learning.support|learning|academic|الدعم الدراسي|Learning support|ما نوع المساعدة الذي يفيد الطفل أثناء التعلم؟|What kind of help supports the child's learning?
learning.concern|learning|academic|ملاحظة الأسرة الدراسية|Family's learning concern|ما الصعوبة الدراسية التي تريد الأسرة دعمها؟|Which learning difficulty would the family like help with?'''
fields = []
for row in rows.splitlines():
    key, domains, section, ar_label, en_label, ar_question, en_question = row.split('|')
    fields.append({'key': key, 'domains': domains.split(), 'source_section': section,
        'label': {'ar': ar_label, 'en': en_label}, 'question': {'ar': ar_question, 'en': en_question},
        'sensitive': section in ['family', 'pregnancy', 'birth'] and not key.startswith('child.')})
form = 'نموذج دراسة الحالة محمود (1).doc'
manifest['sources'].append({'file': form, 'sha256': hashlib.sha256((source/form).read_bytes()).hexdigest()})
catalogue = {'version': manifest['version'], 'source_file': form, 'clinical_evidence': False,
    'purpose': 'Optional adapted questions; not a diagnostic checklist. Blank form options are never child facts.',
    'fields': fields}
(destination/'case_study_fields.json').write_text(json.dumps(catalogue, ensure_ascii=False, indent=2)+'\n')
(destination/'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2)+'\n')
print(f'Imported matching bilingual dialogue trees, 3 addenda, and {len(fields)} optional case fields.')
