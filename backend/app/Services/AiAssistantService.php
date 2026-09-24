<?php

namespace App\Services;

use App\Models\LabSetting;
use Illuminate\Support\Facades\Http;

class AiAssistantService
{
    /**
     * Sends the patient's message to the lab's configured OpenAI-compatible
     * chat completions endpoint, with a system prompt that:
     *  - grounds the assistant in this lab's REAL doctors and REAL
     *    test/package prices (never invents names or prices),
     *  - keeps it to general, educational information rather than a
     *    diagnosis, and pushes the patient toward an actual doctor,
     *  - only mentions the discount note the lab itself configured.
     *
     * Throws \RuntimeException with an Arabic message on any failure
     * (not configured, request failed, etc.) - callers should show that
     * message to the patient rather than a stack trace.
     */
    public function chat(LabSetting $settings, string $patientMessage, array $context, array $history = []): string
    {
        $config = $settings->ai_config ?? [];
        if (empty($config['enabled']) || empty($config['api_key']) || empty($config['base_url'])) {
            throw new \RuntimeException('المساعد الذكي غير مفعّل حالياً بهذا المختبر');
        }

        $systemPrompt = $this->buildSystemPrompt($context, $config['promo_code_note'] ?? null);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach (array_slice($history, -8) as $turn) {
            if (! empty($turn['role']) && ! empty($turn['content']) && in_array($turn['role'], ['user', 'assistant'], true)) {
                $messages[] = ['role' => $turn['role'], 'content' => mb_substr((string) $turn['content'], 0, 2000)];
            }
        }
        $messages[] = ['role' => 'user', 'content' => mb_substr($patientMessage, 0, 2000)];

        $baseUrl = rtrim($config['base_url'], '/');

        try {
            $response = Http::withToken($config['api_key'])
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $config['model'] ?: 'gpt-4o-mini',
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 700,
                ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('تعذر الاتصال بخدمة الذكاء الاصطناعي حالياً');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('تعذر الحصول على رد من المساعد الذكي حالياً');
        }

        $reply = $response->json('choices.0.message.content');
        if (! $reply) {
            throw new \RuntimeException('لم يصل رد من المساعد الذكي');
        }

        return $reply;
    }

    private function buildSystemPrompt(array $context, ?string $promoNote): string
    {
        $doctorsList = collect($context['doctors'] ?? [])
            ->map(fn ($d) => "- {$d['name']}".($d['specialty'] ? " ({$d['specialty']})" : ''))
            ->implode("\n") ?: 'لا يوجد أطباء مسجلين حالياً.';

        $testsList = collect($context['tests'] ?? [])
            ->map(fn ($t) => "- {$t['name']}: {$t['price']}")
            ->implode("\n") ?: 'لا توجد بيانات أسعار متاحة حالياً.';

        $packagesList = collect($context['packages'] ?? [])
            ->map(fn ($p) => "- {$p['name']}: {$p['price']}")
            ->implode("\n") ?: 'لا توجد باقات متاحة حالياً.';

        $labName = $context['lab_name'] ?? 'المختبر';

        $promoLine = $promoNote
            ? "إذا كان الحديث مناسباً (المريض يفكر بحجز موعد أو فحص)، يمكنك إخبار المريض بهذا العرض حرفياً مرة واحدة فقط: \"{$promoNote}\"."
            : '';

        return <<<PROMPT
أنت مساعد معلومات صحية عام لصالح {$labName}، تتحدث مع مريض عبر بوابته الخاصة. هذا ليس استشارة طبية ولا تشخيصاً، وعليك الالتزام الصارم بما يلي:

القواعد الإلزامية:
1. لا تقدّم تشخيصاً طبياً قطعياً أبداً. تحدث بصيغة احتمالات عامة فقط ("قد يرتبط هذا بـ..."، "من الأسباب الشائعة لهذا العرض...").
2. لا تقترح أي دواء أو جرعة أو علاج محدد إطلاقاً.
3. في نهاية كل رد، ذكّر المريض بوضوح أن هذا لا يغني عن زيارة طبيب مختص لتقييم حالته.
4. إذا ذكر المريض أعراضاً قد تكون طارئة أو خطيرة (مثل ألم صدر شديد، صعوبة تنفس حادة، نزيف شديد، فقدان وعي، أفكار إيذاء النفس)، انصحه فوراً وبوضوح بالتوجه لأقرب طوارئ أو الاتصال بالإسعاف، وتوقف عن أي نقاش آخر غير هذا.
5. لا تخترع أسماء أطباء أو فحوصات أو أسعار غير المذكورة أدناه إطلاقاً - إذا لم يتوفر ما يناسب حالة المريض من القائمة، قل ذلك بصراحة.
6. تحدث بالعربية بأسلوب واضح ومتعاطف ومختصر (فقرات قصيرة).

الأطباء المتوفرون بهذا المختبر (اقترح الأنسب من بينهم فقط حسب اختصاصهم، ولا تقترح أي طبيب من خارج هذه القائمة):
{$doctorsList}

الفحوصات المتوفرة وأسعارها:
{$testsList}

الباقات المتوفرة وأسعارها:
{$packagesList}

{$promoLine}

هيكل الرد المتوقع منك:
- فقرة قصيرة عامة عن الحالة الموصوفة (بدون تشخيص قطعي).
- اقتراح 1-3 فحوصات مناسبة من القائمة أعلاه إن وجدت.
- اقتراح طبيب مختص مناسب من القائمة أعلاه إن وجد.
- تذكير بضرورة مراجعة الطبيب لتأكيد التشخيص.
PROMPT;
    }
}
