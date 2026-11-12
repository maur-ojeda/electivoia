<?php

namespace App\Tests\Unit\Service;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Test double for ResponseStreamInterface — iterates over a fixed array of ChunkInterface mocks.
 * The key() method is never called by GeminiChatbotService, so it throws for safety.
 */
class MockResponseStream implements ResponseStreamInterface, \Iterator
{
    private int $position = 0;

    /** @param ChunkInterface[] $chunks */
    public function __construct(private readonly array $chunks) {}

    public function current(): ChunkInterface { return $this->chunks[$this->position]; }
    public function key(): ResponseInterface   { throw new \LogicException('key() not used in service'); }
    public function next(): void               { $this->position++; }
    public function valid(): bool              { return isset($this->chunks[$this->position]); }
    public function rewind(): void             { $this->position = 0; }
}
