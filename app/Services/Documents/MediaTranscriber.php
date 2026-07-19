<?php

namespace App\Services\Documents;

use App\Services\AI\OpenAiConfigResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MediaTranscriber
{
    public function __construct(private OpenAiConfigResolver $configResolver)
    {
    }

    /**
     * Convert media to bounded audio segments, then transcribe every segment.
     *
     * @return array<int, array{page: int, text: string}>
     */
    public function transcribe(string $path): array
    {
        $ffmpeg = (new ExecutableFinder())->find('ffmpeg');

        if ($ffmpeg === null) {
            throw new RuntimeException('ffmpeg is required to transcribe audio/video knowledge files.');
        }

        $apiKey = $this->configResolver->apiKey();

        if ($apiKey === null || trim($apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY is required to transcribe audio/video knowledge files.');
        }

        $tempDirectory = $this->makeTempDirectory();

        try {
            $segmentPattern = $tempDirectory.DIRECTORY_SEPARATOR.'segment-%04d.mp3';
            $segmentSeconds = max(60, (int) config('ai.transcription_segment_seconds', 1200));
            $process = new Process([
                $ffmpeg,
                '-hide_banner',
                '-loglevel',
                'error',
                '-y',
                '-i',
                $path,
                '-vn',
                '-ac',
                '1',
                '-ar',
                '16000',
                '-b:a',
                '64k',
                '-f',
                'segment',
                '-segment_time',
                (string) $segmentSeconds,
                '-reset_timestamps',
                '1',
                $segmentPattern,
            ]);
            $process->setTimeout(max(300, (int) config('ai.document_extraction_command_timeout', 900)));
            $process->mustRun();

            $segments = glob($tempDirectory.DIRECTORY_SEPARATOR.'segment-*.mp3') ?: [];
            sort($segments, SORT_NATURAL);

            if ($segments === []) {
                throw new RuntimeException('ffmpeg did not produce any audio segments from the media file.');
            }

            $pages = [];

            foreach ($segments as $index => $segment) {
                $contents = file_get_contents($segment);

                if ($contents === false) {
                    throw new RuntimeException('Unable to read a generated audio segment.');
                }

                $request = Http::withToken($apiKey)
                    ->connectTimeout(20)
                    ->timeout(300)
                    ->retry(2, 1000)
                    ->attach('file', $contents, basename($segment));

                $organization = $this->configResolver->organization();

                if ($organization !== null && trim($organization) !== '') {
                    $request = $request->withHeaders([
                        'OpenAI-Organization' => trim($organization),
                    ]);
                }

                $response = $request->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => (string) config('ai.transcription_model', 'gpt-4o-mini-transcribe'),
                    'response_format' => 'json',
                ]);

                if ($response->failed()) {
                    throw new RuntimeException(sprintf(
                        'OpenAI transcription failed with HTTP %d: %s',
                        $response->status(),
                        mb_substr((string) $response->body(), 0, 800)
                    ));
                }

                $text = trim((string) $response->json('text', ''));

                if ($text !== '') {
                    $pages[] = [
                        'page' => $index + 1,
                        'text' => $text,
                    ];
                }

                Log::info('knowledge.transcription.segment_completed', [
                    'file' => basename($path),
                    'segment' => $index + 1,
                    'segments_total' => count($segments),
                    'characters' => mb_strlen($text),
                ]);
            }

            if ($pages === []) {
                $pages = $this->describeVideoFrames($path, $ffmpeg);
            }

            if ($pages === []) {
                throw new RuntimeException(
                    'The media contained no transcribable speech and no video frames that could be described.'
                );
            }

            return $pages;
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    /**
     * Use representative video frames when a video contains no transcribable speech.
     *
     * @return array<int, array{page: int, text: string}>
     */
    private function describeVideoFrames(string $path, string $ffmpeg): array
    {
        $interval = max(10, (int) config('ai.video_frame_interval_seconds', 60));
        $maxFrames = max(1, min(30, (int) config('ai.video_max_frames', 12)));
        $tempDirectory = $this->makeTempDirectory();

        try {
            $framePattern = $tempDirectory.DIRECTORY_SEPARATOR.'frame-%04d.jpg';
            $process = new Process([
                $ffmpeg,
                '-hide_banner',
                '-loglevel',
                'error',
                '-y',
                '-i',
                $path,
                '-vf',
                "fps=1/{$interval},scale=1280:-2:force_original_aspect_ratio=decrease",
                '-frames:v',
                (string) $maxFrames,
                '-q:v',
                '3',
                $framePattern,
            ]);
            $process->setTimeout(max(300, (int) config('ai.document_extraction_command_timeout', 900)));
            $process->run();

            $frames = glob($tempDirectory.DIRECTORY_SEPARATOR.'frame-*.jpg') ?: [];
            sort($frames, SORT_NATURAL);

            if ($frames === []) {
                Log::warning('knowledge.video.frames_unavailable', [
                    'file' => basename($path),
                    'message' => trim($process->getErrorOutput()),
                ]);
                return [];
            }

            $pages = [];

            foreach ($frames as $index => $frame) {
                $description = trim(app(ImageDescriber::class)->describe($frame));

                if ($description === '') {
                    continue;
                }

                $elapsedSeconds = $index * $interval;
                $pages[] = [
                    'page' => $index + 1,
                    'text' => sprintf(
                        "Video frame near %02d:%02d\n%s",
                        intdiv($elapsedSeconds, 60),
                        $elapsedSeconds % 60,
                        $description
                    ),
                ];
            }

            Log::info('knowledge.video.vision_fallback_completed', [
                'file' => basename($path),
                'frames' => count($frames),
                'descriptions' => count($pages),
                'interval_seconds' => $interval,
            ]);

            return $pages;
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    private function makeTempDirectory(): string
    {
        $base = storage_path('app/knowledge-tmp');

        if (!is_dir($base) && !mkdir($base, 0775, true) && !is_dir($base)) {
            throw new RuntimeException('Unable to create the knowledge temporary directory.');
        }

        $path = $base.DIRECTORY_SEPARATOR.'media-'.bin2hex(random_bytes(8));

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create a media transcription directory.');
        }

        return $path;
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
