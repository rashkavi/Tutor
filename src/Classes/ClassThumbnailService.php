<?php
declare(strict_types=1);

namespace Enlixo\Classes;

use Enlixo\Ai\GeminiImageClient;
use Enlixo\Core\HttpException;

/**
 * "Generate thumbnail" on the class form: builds a prompt from the class details the tutor has filled in
 * and asks the image model for a 16:9 cover. The tutor then crops it and it is saved with the class like an upload.
 */
final class ClassThumbnailService
{
    public const KIND = 'class_thumbnail';
    public const STYLES = [
        'bold' => 'Bold poster: deep blue background with soft light effects, a large bold headline, clean modern education look.',
        'photo' => 'Photo style: a bright, realistic study scene that matches the subject (books, lab, classroom), with the headline on a dark band so it is easy to read.',
        'minimal' => 'Minimal: plain light background, simple flat icons for the subject, lots of space, a clear dark headline.',
    ];

    public function __construct(
        private readonly ClassFormRepository $repo,
        private readonly GeminiImageClient $ai,
        private readonly int $dailyLimit,
    ) {
    }

    /** @return array{image:string, remaining:int} image is a data: URL */
    public function generate(int $tutorId, array $in, string $now, string $dayStart): array
    {
        if (!$this->ai->configured()) {
            throw new HttpException(503, 'ai_not_configured', 'AI thumbnails are not set up yet. Ask your ENLIXO admin to add a Gemini API key.');
        }
        $used = $this->repo->aiUsageToday($tutorId, self::KIND, $dayStart);
        if ($used >= $this->dailyLimit) {
            throw new HttpException(429, 'ai_daily_limit', "You can make {$this->dailyLimit} thumbnails a day. Please try again tomorrow, or upload your own image.");
        }

        $prompt = self::prompt($this->details($tutorId, $in), (string) ($in['style'] ?? 'bold'));
        // Count the try before calling, so failed or repeated clicks can't run up the bill.
        $this->repo->logAiUsage($tutorId, self::KIND, $now);
        $img = $this->ai->generate($prompt);

        return ['image' => 'data:' . $img['mime'] . ';base64,' . base64_encode($img['bytes']), 'remaining' => max(0, $this->dailyLimit - $used - 1)];
    }

    /** Labels for the chosen ids (only options this tutor can see) plus the free text fields, trimmed. */
    private function details(int $tutorId, array $in): array
    {
        $lookups = $this->repo->lookups($tutorId);
        $label = static function (string $list, $id) use ($lookups): string {
            foreach ($lookups[$list] as $o) {
                if ($o['value'] === (string) $id) {
                    return $o['label'];
                }
            }
            return '';
        };
        $d = [
            'subject' => $label('subjects', $in['subject'] ?? ''),
            'exam' => $label('exam_types', $in['exam_type'] ?? ''),
            'year' => $label('years', $in['year'] ?? ''),
            'medium' => $label('mediums', $in['medium'] ?? ''),
            'type' => ['theory' => 'Theory', 'revision' => 'Revision', 'paper' => 'Paper class'][$in['class_type'] ?? ''] ?? '',
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 100),
            'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 300),
        ];
        if ($d['subject'] === '') {
            throw HttpException::validation(['subject' => 'Choose the subject first, so the picture matches your class.']);
        }
        return $d;
    }

    public static function prompt(array $d, string $style): string
    {
        $headline = $d['subject'];
        $subline = trim(implode(' ', array_filter([$d['year'], $d['exam'], $d['type']])));
        $lines = [
            'Create a wide 16:9 cover image for an online and physical tuition class in Sri Lanka.',
            self::STYLES[$style] ?? self::STYLES['bold'],
            "Main headline text, large and spelled exactly: \"$headline\".",
            $subline !== '' ? "Smaller second line, spelled exactly: \"$subline\"." : '',
            $d['medium'] !== '' && $d['medium'] !== 'English' ? "The class is taught in {$d['medium']}, but keep all text in the image in English." : '',
            $d['description'] !== '' ? 'What the class covers (for the picture only, do not write this text): ' . $d['description'] : '',
            'Keep the text inside the middle 80% of the image so it is not cut off when cropped.',
            'No logos, no brand names, no watermarks, no phone numbers, no real people\'s faces, no other text.',
        ];
        return implode("\n", array_filter($lines));
    }
}
