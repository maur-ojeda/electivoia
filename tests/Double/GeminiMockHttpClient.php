<?php

namespace App\Tests\Double;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Deterministic double for the Gemini HTTP boundary used by GeminiChatbotService
 * and GeminiInsightsService in the `test` environment. Wired via
 * config/services_test.yaml so no test ever reaches the real Gemini API.
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
            json_encode(
                ['candidates' => [['content' => ['parts' => [['text' => self::REPLY]]]]]],
                JSON_THROW_ON_ERROR,
            ),
            ['response_headers' => ['content-type' => 'application/json']],
        ));
    }
}
