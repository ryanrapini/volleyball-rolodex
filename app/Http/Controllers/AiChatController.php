<?php

namespace App\Http\Controllers;

use App\Support\Ai\RolodexAssistant;
use Illuminate\Http\Client\RequestException;
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
            'messages' => ['present', 'array', 'max:200'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            // No length cap on a single message: a long paste of names is a
            // reasonable thing to send. The ceiling is the model's context
            // window, which is reported back clearly if it is reached.
            'messages.*.content' => ['required', 'string'],
        ]);

        if (! RolodexAssistant::isConfigured()) {
            return response()->json([
                'error' => 'The assistant is not configured yet — an OpenAI API key is missing.',
            ], 503);
        }

        try {
            $result = (new RolodexAssistant($request->user()))->reply($data['messages']);
        } catch (RequestException $e) {
            report($e);

            if ($this->ranOutOfContext($e)) {
                return response()->json([
                    'error' => 'That conversation is longer than the model can read. Clear the transcript and try again.',
                ], 422);
            }

            return response()->json(['error' => 'The assistant could not be reached just now.'], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The assistant could not be reached just now.'], 502);
        }

        return response()->json($result);
    }

    /**
     * The model rejects a transcript that no longer fits its context window with
     * a 400; saying which limit was hit beats a generic failure.
     */
    private function ranOutOfContext(RequestException $e): bool
    {
        $body = mb_strtolower((string) $e->response?->body());

        return $e->response?->status() === 400
            && (str_contains($body, 'context') || str_contains($body, 'too long'));
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
