<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\AssemblyAIService;
use App\Services\TranscriptionService;
use App\Services\OpenAIService;

class ReadingAnalysisController extends Controller
{
    protected $transcriptionService;
    protected $openAIService;

    public function __construct(TranscriptionService $transcriptionService, OpenAIService $openAIService)
    {
        $this->transcriptionService = $transcriptionService;
        $this->openAIService = $openAIService;
    }

    public function analyze(Request $request)
    {
        try {
            Log::info('Analyze process started.');

            $request->validate([
                'file' => 'required|file|mimes:mp3|max:10240',
                'text' => 'required|string',
            ], [
                'file.required' => 'Audio file is required.',
                'file.file' => 'The uploaded file must be a valid file.',
                'file.mimes' => 'Only mp3 file formats are allowed.',
                'file.max' => 'File size cannot exceed 10MB.',
                'text.required' => 'Text input is required.',
                'text.string' => 'Text input must be a valid string.',
            ]);

            $file = $request->file('file');
            $inputText = $request->input('text');

            $transcription = $this->transcriptionService->getTranscription(
                app(AssemblyAIService::class)->uploadAudio($file)
            );

            $accuracy = $this->calculateAccuracy($inputText, $transcription['text']);

            Log::info('Accuracy calculated', ['accuracy' => $accuracy]);

            $response = $this->openAIService->compareTexts($inputText, $transcription['text']);

            $analyze = $response['analysis'];
            $feedback = $this->extractFeedback($analyze);
            $correctedTranscription = $this->extractCorrectedTranscription($analyze);

            Log::info('FEEDBACK COMPLETED', ['feedback' => $feedback]);
            Log::info('correctedTranscription COMPLETED', ['corrected_transcription' => $correctedTranscription]);

            Log::info('AssemblyAI Cost:', ['cost' => $transcription['cost']]);
            Log::info('OpenAI Response:', ['response' => $response]);

            Log::info('Analyze process completed successfully.');

            return response()->json([
                'transcription' => $transcription['text'] ?? '',
                'analysis' => $analyze ?? '',
                'reading_speed' => $transcription['reading_speed'] ?? 0,
                'feedback' => $feedback,
                'corrected_transcription' => $correctedTranscription,
                'accuracy' => $accuracy,
                'cost' => [
                    'assemblyAI' => $transcription['cost'] ?? 0,
                    'openAI' => $response['cost'] ?? 0
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation error:', ['errors' => $e->errors()]);
            return response()->json(['error' => 'Validation error occurred', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error during analyze process.', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'An error occurred. Please check your file type and size.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateAccuracy($originalText, $transcribedText)
    {
        $cleanText = function ($text) {
            return strtolower(
                preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) // Unicode destekli harf ve sayı karakterleri
            );
        };

        $cleanedOriginalText = $cleanText($originalText);
        $cleanedTranscribedText = $cleanText($transcribedText);

        $originalWords = array_filter(explode(' ', $cleanedOriginalText));
        $transcribedWords = array_filter(explode(' ', $cleanedTranscribedText));

        $originalWordCount = count($originalWords);
        $transcribedWordCount = count($transcribedWords);

        $matchCount = 0;
        $usedIndexes = [];

        foreach ($originalWords as $originalIndex => $originalWord) {
            foreach ($transcribedWords as $transcribedIndex => $transcribedWord) {
                if (
                    $originalWord === $transcribedWord &&
                    !in_array($transcribedIndex, $usedIndexes) // Aynı kelimenin iki kez eşleşmesini engelle
                ) {
                    $matchCount++;
                    $usedIndexes[] = $transcribedIndex; // Bu transkripsiyon kelimesi eşleşti, bir daha eşleşmesin
                    break;
                }
            }
        }

        $accuracy = ($matchCount / max($originalWordCount, $transcribedWordCount)) * 100;

        return round($accuracy, 2);
    }


    private function extractFeedback($analysis)
    {
        preg_match('/\*\*Feedback\*\*\s*(.*?)\*\*Corrected Transcription\*\*/s', $analysis, $matches);

        Log::info('Extracted Feedback Matches:', ['matches' => $matches]);

        return $matches[1] ?? "No feedback available.";
    }

    private function extractCorrectedTranscription($analysis)
    {
        preg_match('/\*\*Corrected Transcription\*\*\s*(.*)/s', $analysis, $matches);

        Log::info('Extracted Corrected Transcription Matches:', ['matches' => $matches]);

        return $matches[1] ?? "No transcription available.";
    }

}
