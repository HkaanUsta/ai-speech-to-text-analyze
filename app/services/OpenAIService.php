<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected $apiKey;
    protected $url;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->url = "https://api.openai.com/v1/chat/completions";
    }

    public function compareTexts($originalText, $transcribedText)
    {
        $prompt = $this->generatePrompt($originalText, $transcribedText);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->url, [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert reading analyzer for children\'s reading performance.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ]);

        if (!$response->successful()) {
            Log::error("OpenAI API call failed.", ['response' => $response->body()]);
            throw new \Exception('OpenAI API call failed: ' . $response->body());
        }

        $responseData = $response->json();

        if (empty($responseData['choices'][0]['message']['content'])) {
            Log::error('OpenAI response content is empty or null.');
            throw new \Exception('OpenAI response content is empty.');
        }

        $analysis = $responseData['choices'][0]['message']['content'];

        $cost = $this->calculateOpenAICost($responseData['usage']['total_tokens'] ?? 0);

        return [
            'analysis' => $analysis,
            'cost' => $cost,
        ];
    }

    private function calculateOpenAICost($tokenCount)
    {
        $costPerThousandTokens = 0.03;
        return round(($tokenCount / 1000) * $costPerThousandTokens, 4);
    }

    private function generatePrompt($originalText, $transcribedText)
    {
        return <<<PROMPT
Analyze the child's reading performance by comparing the original text with the transcribed text.

### Rules and Definitions:

1. **Ignore punctuation and whitespace differences**:
   - Completely ignore all punctuation marks such as commas (,), periods (.), ellipses (...), and other symbols.
   - Punctuation should not trigger additions, omissions, or replacements. Analyze only the words.

2. **Error Types**:
   Apply these tags exactly as defined. Do not deviate from the format.

   - **Addition**:
     Transcribed text has a word/phrase not in the original.
     Format: `[transcribed_word|addition]`
     - **Special Case for Additions**:
       If the added word is empty (e.g., `[|addition]`), or consists of only whitespace or punctuation, do not include this tag in the output.

     Example:
       Original: "The cat sleeps on the mat"
       Transcription: "The cat sleeps on the soft mat"
       Result: "The cat sleeps on the [soft|addition] mat"

   - **Omission**:
     A word/phrase from the original is missing entirely in the transcription.
     Format: `[missing_original_word|omission]`
     - **Special Case for Omissions**:
       Do not include tags like `[|omission]` if the missing word is empty or consists of whitespace only.

     Example 1:
       Original: "The quick brown fox"
       Transcription: "The brown fox"
       Result: "The [quick|omission] brown fox"

     Example 2:
       Original: "The brown fox jumped over the lazy dog"
       Transcription: "The brown fox jump over the lazy dog"
       Result: "The brown fox jump[ed|omission] over the lazy dog"

     Note: The `missing_original_word` must be exactly as it appears in the original text.

   - **Replacement**:
     A word/phrase in the transcription replaces a word/phrase in the original.
     Format: `[transcribed_word|replacement|original_word]`
     - `transcribed_word`: the incorrect word/phrase from the transcription.
     - `original_word`: the correct word/phrase from the original text.

     Example 1:
       Original: "The quick brown fox"
       Transcription: "The lazy brown fox"
       Result: "The [lazy|replacement|quick] brown fox"

     Example 2:
       Original: "He played the piano beautifully"
       Transcription: "He played the piano softly"
       Result: "He played the piano [softly|replacement|beautifully]"

     Important: Do not mark a replacement as an omission or addition. If a portion of the word is missing, changed, or expanded, consider the entire differing segment as a replacement. Ensure `original_word` and `transcribed_word` reflect the exact words from each text.

   - **Repetition**:
     A word/phrase is unnecessarily repeated.
     Format: `[repeated_word|repetition]`

     Example:
       Original: "She is running fast"
       Transcription: "She is is running fast"
       Result: "She [is|repetition] running fast"

     Only mark the extra occurrence as repetition, not the first correct one.

   - **Reversal**:
     A word/phrase is reversed.
     Format: `[reversed_word|reversal|original_word]`

     Example:
       Original: "dog"
       Transcription: "god"
       Result: "[god|reversal|dog]"

3. **Preserve Structure**:
   - Do not reorder words.
   - Insert tags inline exactly where differences occur.
   - For omissions, place the tag at the exact position where the missing word should be.
   - For replacements, only replace the differing word/phrase and retain the sentence structure.

4. **No Alteration of Correct Words**:
   - If a word is correct and matches the original exactly, leave it as is.

5. **Feedback and Corrected Transcription**:
   - First, produce a "**Feedback**" section that:
     - Summarizes the child's performance.
     - Counts how many additions, omissions, replacements, repetitions, and reversals occurred.
     - Describes the most common issues.
   - Then, produce a "**Corrected Transcription**" section that shows the entire text, line by line, with inline tags where errors occur.
   - The format must be exactly:
     ```
     **Feedback**
     <Your feedback here>

     **Corrected Transcription**
     <Your corrected transcription here>
     ```

6. **No Extra Text**:
   - Do not include explanations, reasoning steps, or commentary outside of the required format.

7. **Follow the Rules Strictly**:
   - Ensure that for each replacement, `[transcribed_word|replacement|original_word]` is correct. The `original_word` must match exactly what is in the original text, and `transcribed_word` must match exactly what is in the transcription.
   - For omissions, `[missing_original_word|omission]` must exactly match the missing word from the original text.
   - For additions, ensure that the added word is not present in the original text.
   - For repetitions, only the repeated instance (the second or subsequent occurrence) should be tagged.

   - **Special Case for Punctuation and Whitespace**:
     - Punctuation (e.g., commas, periods, etc.) and extra/missing spaces must not trigger any tags.
     - Do not include `[|addition]` or `[|omission]` if the added or missing word is punctuation or whitespace.

### Input Data:

**Original Text:**
$originalText

**Transcribed Text:**
$transcribedText

### Instructions to the Model:
1. Read the rules carefully and apply them exactly.
2. Produce only the required output (Feedback and Corrected Transcription) in the specified format.
3. No extra commentary, no reasoning steps, just the result.

PROMPT;
    }



}
