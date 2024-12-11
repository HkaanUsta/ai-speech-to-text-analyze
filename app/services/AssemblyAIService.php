<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssemblyAIService
{
    protected $apiKey;
    protected $uploadEndpoint;
    protected $transcriptionEndpoint;

    public function __construct()
    {
        $this->apiKey = env('ASSEMBLYAI_API_KEY');
        $this->uploadEndpoint = "https://api.assemblyai.com/v2/upload";
        $this->transcriptionEndpoint = "https://api.assemblyai.com/v2/transcript";
    }

    public function uploadAudio($file)
    {
        $mimeType = $file->getMimeType();

        Log::info($mimeType);

        $upload_response = Http::withHeaders([
            'authorization' => $this->apiKey,
            'content-type' => $mimeType,
        ])->attach(
            'file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName()
        )->post($this->uploadEndpoint);


        if (!$upload_response->successful() || !isset($upload_response['upload_url'])) {
            Log::error("File upload failed.", ['response' => $upload_response->body()]);
            throw new \Exception('File upload failed');
        }

        $FILE_URL = $upload_response['upload_url'];

        $transcript_response = Http::withHeaders([
            'authorization' => $this->apiKey,
            'content-type' => $mimeType,
        ])->post($this->transcriptionEndpoint, [
            'audio_url' => $FILE_URL,
            'language_code' => 'tr',
        ]);

        if (!$transcript_response->successful() || !isset($transcript_response['id'])) {
            Log::error("Failed to submit file for transcription.", ['response' => $transcript_response->body()]);
            throw new \Exception('Failed to submit transcription');
        }

        return $transcript_response['id'];
    }

    public function checkTranscriptionStatus($transcript_id)
    {
        $polling_endpoint = "https://api.assemblyai.com/v2/transcript/{$transcript_id}";

        $polling_response = Http::withHeaders([
            'authorization' => $this->apiKey,
            'content-type' => 'application/json',
        ])->get($polling_endpoint);
        Log::info("Polling response:", $polling_response->json());
        if (!$polling_response->successful()) {
            Log::error("Polling failed.", ['response' => $polling_response->body()]);
            throw new \Exception('Polling failed');
        }

        return $polling_response->json();
    }
}
