<?php

namespace App\Services;

use App\Services\AssemblyAIService;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    protected $assemblyAIService;

    public function __construct(AssemblyAIService $assemblyAIService)
    {
        $this->assemblyAIService = $assemblyAIService;
    }

    public function getTranscription(string $transcriptId): array
    {
        Log::info('Waiting for transcription.', ['transcript_id' => $transcriptId]);

        //Bu kısımda assembly ai'ın webhook özelliğinin eksik olmasından dolayı cevabı almak için while döngüsüne ihtiyacımız olmaktadır
        for ($i = 0; $i < 100; $i++) {
            $transcription = $this->assemblyAIService->checkTranscriptionStatus($transcriptId);

            Log::info('Polling result.', ['polling_attempt' => $i + 1, 'transcription' => $transcription]);

            if ($transcription['status'] === 'completed') {
                $words = $transcription['words'];

                $readingSpeed = $this->calculateWPM($words);

                $cost = $this->calculateAssemblyAICost($transcription['audio_duration']);

                return [
                    'text' => $transcription['text'],
                    'words' => $words,
                    'reading_speed' => $readingSpeed,
                    'cost' => $cost,
                ];
            } elseif ($transcription['status'] === 'error') {
                throw new \Exception('Transcription failed: ' . $transcription['error']);
            }

            sleep(3);
        }

        throw new \Exception('Transcription timeout');
    }

    private function calculateAssemblyAICost($durationInSeconds)
    {
        $costPerMinute = 0.0062;
        $durationInMinutes = $durationInSeconds / 60;
        return round($durationInMinutes * $costPerMinute, 4);
    }


    private function calculateWPM($words)
    {
        if (empty($words)) {
            return 0;
        }

        $startTime = $words[0]['start'];
        $endTime = $words[count($words) - 1]['end'];

        $readingDuration = ($endTime - $startTime) / 1000;

        $wordCount = count($words);
        $wpm = ($wordCount / $readingDuration) * 60;

        return round($wpm, 2);
    }
}
