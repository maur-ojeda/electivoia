<?php

namespace App\Tests\Double;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Deterministic double for the AI provider HTTP boundary used by
 * GeminiChatbotService and GeminiInsightsService in the `test` environment.
 * Wired via config/services_test.yaml so no test ever reaches the real API.
 *
 * Both services consume an OpenRouter-style SSE stream
 * (`data: {"choices":[{"delta":{"content":"..."}}]}`), so the double must speak
 * that format. It previously returned a Gemini `candidates[]` payload, which the
 * streaming parser silently skipped, leaving every mocked reply empty.
 *
 * A callable response factory (not a fixed response list) yields an unlimited
 * number of identical canned responses, so no test can exhaust the queue.
 */
final class GeminiMockHttpClient extends MockHttpClient
{
    public const REPLY = 'Respuesta simulada del asistente para pruebas.';

    public function __construct()
    {
        parent::__construct(static fn (): MockResponse => new MockResponse(
            'data: '.json_encode(
                ['choices' => [['delta' => ['content' => self::REPLY]]]],
                JSON_THROW_ON_ERROR,
            )."\n\ndata: [DONE]\n\n",
            ['response_headers' => ['content-type' => 'text/event-stream']],
        ));
    }
}
