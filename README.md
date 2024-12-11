# Speech-to-Text Reading Analysis

## Overview

This project is a web application that analyzes a child’s reading performance by comparing an audio recording of their reading to the original text. It integrates speech-to-text services and OpenAI's GPT API for analyzing errors and providing feedback on the reading.

## Features

- **Audio Transcription**: Converts uploaded audio files into text using AssemblyAI.
- **Error Detection**: Compares the transcribed text with the original and highlights:
    - Additions
    - Omissions
    - Replacements
    - Repetitions
    - Reversals
- **Feedback Generation**: Uses OpenAI to classify errors and provide feedback.
- **Reading Speed Calculation**: Calculates words per minute (WPM) using timestamps from transcription.
- **Cost Tracking**: Displays the cost of API usage for both transcription and analysis.

## Requirements

- Node.js
- Laravel (for backend)
- AssemblyAI API key
- OpenAI API key

## Installation

### Backend

1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/speech-to-text-backend.git
   cd speech-to-text-backend
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Set up environment variables:
    - Create a `.env` file in the root directory and add the following:
      ```env
      OPENAI_API_KEY=your_openai_api_key
      ASSEMBLYAI_API_KEY=your_assemblyai_api_key
      ```
4. Start the Laravel server:
   ```bash
   php artisan serve
   ```

### Frontend

1. Clone the repository:
   ```bash
   git clone https://github.com/your-repo/speech-to-text-frontend.git
   cd speech-to-text-frontend
   ```
2. Install dependencies:
   ```bash
   npm install
   ```
3. Start the development server:
   ```bash
   npm run dev
   ```

## Usage

1. Open the application in your browser (e.g., `http://localhost:3000`).
2. Upload the original reading text or type it manually.
3. Record or upload an audio file of the child’s reading.
4. Submit the inputs to analyze.
5. View:
    - Highlighted transcription errors
    - Feedback on reading performance
    - WPM calculation
    - API cost breakdown

## API Details

### AssemblyAI

- **Endpoints Used**:
    - `/v2/upload`: For uploading audio files.
    - `/v2/transcript`: For transcription.
    - `/v2/transcript/{id}`: For polling transcription status.
- **Features**:
    - Word-level timestamps
    - Turkish language support

### OpenAI

- **Endpoint Used**:
    - `/v1/chat/completions`: For error analysis and feedback generation.
- **Model**:
    - `gpt-4`

## File Structure

### Backend

- `app/Services`:
    - `AssemblyAIService.php`: Handles AssemblyAI API integration.
    - `OpenAIService.php`: Handles OpenAI API integration.
    - `TranscriptionService.php`: Manages transcription and reading speed calculations.
- `app/Http/Controllers`:
    - `ReadingAnalysisController.php`: Manages the analysis process.

### Frontend

- `components/layout`:
    - `ReadingInterface.js`: Handles user input and displays results.
    - `InfoCard.js`: Displays analysis details and API costs.
- `pages`:
    - `page.js`: Main application logic.

## Example Input/Output

### Input

- **Original Text**:
  ```
  Bir hafta önce annesiyle ablası çarşıya çıkmışlardı.
  ```
- **Transcripted Text**:
  ```
  Bir hafta önce annesiyle ablası birlikte çarşıya çıkmışlardı.
  ```
### Output
- **Highlighted Errors**:
  ```
  Bir hafta önce annesiyle ablası [birlikte:addition] çarşıya çıkmışlardı.
  ```
- **Feedback**:
  The child's performance was generally good. There was only one error, and it was an addition. The child added the word "birlikte" which was not present in the original text.


- **Reading Speed**: 85.62 WPM
- **Costs**:
    - AssemblyAI: $0.02
    - OpenAI: $0.03

## Contact

For any questions or suggestions, please contact `your-email@example.com`.

