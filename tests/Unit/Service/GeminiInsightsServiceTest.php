<?php

namespace App\Tests\Unit\Service;

use App\Service\GeminiInsightsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Issue #13: the insights generated from the students' interest profiles.
 *
 * The functional test covers the deterministic panel rendered by the page. This
 * test covers the two paths the page cannot observe in the `test` environment,
 * where the provider is mocked: the fallback text (no API key) and the prompt
 * actually sent to the provider.
 */
class GeminiInsightsServiceTest extends TestCase
{
    private const SSE_BODY = "data: {\"choices\":[{\"delta\":{\"content\":\"- ok\"}}]}\n\ndata: [DONE]\n\n";

    public function testFallbackStatesThePreferredCategoryPerGrade(): void
    {
        $service = new GeminiInsightsService(new MockHttpClient(), new NullLogger(), '');

        $insights = $service->generateInsights($this->stats());

        $this->assertContains(
            '- El 70% de los estudiantes de 4M prefiere Artes (7 de 10 perfiles).',
            $insights,
            'Without an AI key the report must still state the per-grade preference taken from the profiles.'
        );
    }

    public function testFallbackExplainsWhenNoProfileWasAnswered(): void
    {
        $service = new GeminiInsightsService(new MockHttpClient(), new NullLogger(), '');
        $stats = $this->stats();
        $stats['interest_by_grade'] = [];

        $insights = $service->generateInsights($stats);

        $this->assertContains(
            '- Todavía no hay perfiles de interés respondidos: los insights se basan solo en la ocupación de los cursos.',
            $insights,
            'An empty profile population must be stated instead of silently ignored.'
        );
    }

    public function testPromptSentToTheProviderIncludesThePerGradePreferences(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'options' => $options];

            return new MockResponse(self::SSE_BODY, ['response_headers' => ['content-type' => 'text/event-stream']]);
        });

        $service = new GeminiInsightsService($httpClient, new NullLogger(), 'test-key');
        $service->generateInsights($this->stats());

        $body = (string) ($captured['options']['body'] ?? '');

        $this->assertStringContainsString(
            'Preferencias declaradas por curso',
            $body,
            'The provider must receive the interest data, otherwise it cannot produce interest-based insights.'
        );
        $this->assertStringContainsString(
            '4M: Artes 70%',
            $body,
            'The prompt must carry the per-grade counts that back the percentages.'
        );
    }

    public function testPromptOmitsTheInterestSectionWhenNoProfileWasAnswered(): void
    {
        $captured = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options = []) use (&$captured): MockResponse {
            $captured = ['url' => $url, 'options' => $options];

            return new MockResponse(self::SSE_BODY, ['response_headers' => ['content-type' => 'text/event-stream']]);
        });

        $stats = $this->stats();
        $stats['interest_by_grade'] = [];

        $service = new GeminiInsightsService($httpClient, new NullLogger(), 'test-key');
        $service->generateInsights($stats);

        $body = (string) ($captured['options']['body'] ?? '');

        $this->assertStringContainsString(
            '- Cursos activos: 3',
            $body,
            'Control case: the occupancy section must still be sent.'
        );
        $this->assertStringNotContainsString(
            'Preferencias declaradas por curso',
            $body,
            'With no answered profile the prompt must stay as it was, instead of announcing an empty section.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        return [
            'total_courses' => 3,
            'total_enrolled' => 20,
            'total_capacity' => 30,
            'avg_occupancy' => 66.7,
            'by_category' => ['Artes' => 10],
            'interest_by_grade' => [
                '4M' => [
                    'total' => 10,
                    'categories' => [
                        ['name' => 'Artes', 'count' => 7, 'pct' => 70.0],
                        ['name' => 'Matemática', 'count' => 3, 'pct' => 30.0],
                    ],
                ],
            ],
        ];
    }
}
