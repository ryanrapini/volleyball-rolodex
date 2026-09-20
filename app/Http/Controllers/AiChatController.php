<?php

namespace App\Http\Controllers;

use App\Support\Ai\RolodexAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiChatController extends Controller
{
    /**
     * Run one turn of the rolodex assistant.
     *
     * The transcript lives in the browser and is replayed with every turn, so
     * there is no server-side conversation to keep in sync.
     */
    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['present', 'array', 'max:40'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:4000'],
        ]);

        if (! RolodexAssistant::isConfigured()) {
            return response()->json([
                'error' => 'The assistant is not configured yet — an OpenAI API key is missing.',
            ], 503);
        }

        try {
            $result = (new RolodexAssistant($request->user()))->reply($data['messages']);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The assistant could not be reached just now.'], 502);
        }

        return response()->json($result);
    }

    /**
     * Turn a short recording into text for the composer.
     */
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'max:10240'],
        ]);

        if (! RolodexAssistant::isConfigured()) {
            return response()->json(['error' => 'Voice input needs an OpenAI API key.'], 503);
        }

        $audio = $request->file('audio');

        try {
            $response = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                ->withToken((string) config('services.openai.key'))
                ->timeout(120)
                ->attach('file', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName() ?: 'voice.webm')
                ->post('/audio/transcriptions', [
                    'model' => (string) config('services.openai.transcribe_model'),
                ])
                ->throw();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Could not make out that recording.'], 502);
        }

        return response()->json(['text' => trim((string) $response->json('text'))]);
    }
}
