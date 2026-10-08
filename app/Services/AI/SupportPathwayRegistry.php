<?php

namespace App\Services\AI;

use RuntimeException;

/** The supplied drafts are routing/question data, never treatment evidence. */
class SupportPathwayRegistry
{
    private ?array $package = null;

    public function package(): array
    {
        if ($this->package === null) {
            $this->package = json_decode(file_get_contents(resource_path('ai/support_pathways.json')), true, 512, JSON_THROW_ON_ERROR);
            $this->validate($this->package);
        }

        return $this->package;
    }

    public function pathways(): array
    {
        return array_merge($this->package()['pathways'], $this->additionalPathways());
    }

    public function pathway(string $id): ?array
    {
        foreach ($this->pathways() as $pathway) {
            if ($pathway['id'] === $id) {
                return $pathway;
            }
        }

        return null;
    }

    public function node(string $id): ?array
    {
        [$pathwayId] = explode(':', $id);
        $nodes = $pathwayId === 'gateway' ? $this->package()['gateway']['nodes'] : ($this->pathway($pathwayId)['nodes'] ?? []);
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }
        }

        return null;
    }

    public function catalogue(): array
    {
        return array_map(fn (array $pathway): array => [
            'id' => $pathway['id'], 'title' => $pathway['title'],
            'review_status' => $pathway['review_status'], 'origin' => $pathway['origin'],
            'age_groups' => $pathway['age_groups'], 'clinical_evidence' => false,
        ], $this->pathways());
    }

    public function validate(array $package): void
    {
        if (($package['clinical_evidence'] ?? null) !== false || count($package['pathways'] ?? []) !== 39) {
            throw new RuntimeException('Invalid supplied support pathway package.');
        }
        $ids = [];
        foreach (array_merge($package['pathways'], [$package['gateway']]) as $pathway) {
            foreach ($pathway['nodes'] as $node) {
                if (isset($ids[$node['id']]) || empty($node['text']['ar']) || empty($node['text']['en'])
                    || ($node['review_status'] ?? '') !== 'draft') {
                    throw new RuntimeException('Invalid or duplicate bilingual pathway node.');
                }
                $ids[$node['id']] = true;
            }
        }
    }

    private function additionalPathways(): array
    {
        // These are authored intake questions to fill explicitly identified
        // gaps. They contain no treatment instructions or diagnostic criteria.
        $questions = [
            'hearing' => [
                'title' => ['ar' => 'احتياجات السمع', 'en' => 'Hearing support needs'],
                'questions' => [
                    ['ما الموقف الذي لاحظت فيه صعوبة الاستجابة للصوت؟', 'In which situation did you notice difficulty responding to sound?'],
                    ['متى بدأ التغير في الاستجابة للصوت؟', 'When did the change in responding to sound start?'],
                    ['هل أُجري فحص سمع سابقًا؟', 'Has a hearing assessment been done before?'],
                    ['هل يوجد ألم بالأذن الآن؟', 'Is there ear pain now?'],
                    ['ما وسيلة التواصل الأوضح للشخص حاليًا؟', 'Which communication method is clearest for the person now?'],
                    ['كيف تؤثر صعوبة السمع على النشاط الذي تريد المساعدة فيه؟', 'How does the hearing difficulty affect the activity you want help with?'],
                ],
            ],
            'down_syndrome_support' => [
                'title' => ['ar' => 'احتياجات الدعم المرتبطة بمتلازمة داون', 'en' => 'Reported Down syndrome support needs'],
                'questions' => [
                    ['هل وردت متلازمة داون في تقييم مهني سابق؟', 'Was Down syndrome reported in a previous professional assessment?'],
                    ['ما المهارة اليومية التي تريد دعمها الآن؟', 'Which daily skill would you like help supporting now?'],
                    ['ما الذي يستطيع الشخص فعله بنفسه في هذه المهارة؟', 'What can the person do independently in that skill?'],
                    ['ما وسيلة التواصل التي يفضلها الشخص؟', 'Which communication method does the person prefer?'],
                    ['هل توجد خطة دعم حالية من مختص؟', 'Is there a current professional support plan?'],
                    ['هل طرأ تغير جديد على الأداء المعتاد؟', 'Has there been a new change in usual functioning?'],
                ],
            ],
        ];
        $pathways = [];
        foreach ($questions as $id => $definition) {
            $nodes = [];
            foreach ($definition['questions'] as $i => [$ar, $en]) {
                $localId = sprintf('D%02d', $i + 1);
                $nodes[] = ['id' => $id.':'.$localId, 'local_id' => $localId, 'kind' => 'question',
                    'review_status' => 'draft', 'text' => ['ar' => $ar, 'en' => $en]];
            }
            $pathways[] = ['id' => $id, 'title' => $definition['title'], 'nodes' => $nodes,
                'origin' => 'authored_gap_intake', 'review_status' => 'draft', 'clinical_evidence' => false,
                'age_groups' => ['child', 'teen', 'adult', 'older_adult'], 'references' => []];
        }

        return $pathways;
    }
}
