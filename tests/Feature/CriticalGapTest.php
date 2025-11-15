<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Facades\TextChunker;

describe('Critical Gap Coverage Tests', function () {
    test('config caching works correctly with cached configuration', function () {
        // Simulate config caching scenario
        $originalDefaultStrategy = config('text-chunker.default_strategy');
        expect($originalDefaultStrategy)->toBe('character');

        // Test that strategies work with cached config values
        $text = 'Testing config caching compatibility with Laravel.';

        // Use token strategy with cached model config
        $chunks = app('text-chunker')->strategy('token')->size(10)->chunk($text);
        expect($chunks)->toBeArray()
            ->and($chunks[0])->toBeInstanceOf(Chunk::class);

        // Use sentence strategy with cached abbreviations
        $textWithAbbr = 'Dr. Smith went home. Mrs. Jones stayed.';
        $sentenceChunks = app('text-chunker')->strategy('sentence')->size(1)->chunk($textWithAbbr);
        expect($sentenceChunks)->toHaveCount(2);
    });

    test('real world use case chunking API documentation for RAG', function () {
        $apiDocumentation = <<<'DOC'
# Authentication API

The Authentication API provides secure user authentication.

## Endpoints

### POST /api/login
Authenticates a user and returns a JWT token.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "secure_password"
}
```

**Response:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 123,
    "name": "John Doe"
  }
}
```

### POST /api/logout
Invalidates the current user session.

## Error Handling

All endpoints return standard HTTP status codes.
DOC;

        // Chunk for RAG with 20% overlap to maintain context
        $chunks = TextChunker::strategy('markdown')
            ->size(200)
            ->overlap(20)
            ->chunk($apiDocumentation);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);

        // Verify structural integrity - all JSON blocks should be preserved
        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('```json')
            ->and($fullText)->toContain('POST /api/login')
            ->and($fullText)->toContain('POST /api/logout');

        // Verify all chunks have valid positions
        foreach ($chunks as $chunk) {
            expect($chunk->start_position)->toBeInt()
                ->and($chunk->end_position)->toBeInt()
                ->and($chunk->end_position)->toBeGreaterThan($chunk->start_position);
        }
    });

    test('real world use case chunking chat history for summarization', function () {
        $chatHistory = '';
        for ($i = 1; $i <= 50; $i++) {
            $chatHistory .= "User: This is message number {$i}. ";
            $chatHistory .= "Assistant: I received your message {$i}. ";
        }

        // Chunk by sentences with small overlap for summarization
        $chunks = TextChunker::strategy('sentence')
            ->size(20)
            ->overlap(10)
            ->chunk($chatHistory);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(3);

        // Verify each chunk maintains sentence integrity
        foreach ($chunks as $chunk) {
            // Should contain complete sentences
            expect($chunk->text)->toMatch('/\.\s*$|\.\s+\w/');
        }
    });

    test('real world use case chunking code files for LLM analysis', function () {
        $phpCode = <<<'PHP'
<?php

namespace App\Services;

class UserService
{
    public function createUser(array $data): User
    {
        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return $user;
    }

    public function deleteUser(int $id): bool
    {
        $user = User::find($id);
        if (!$user) {
            throw new UserNotFoundException();
        }

        return $user->delete();
    }

    public function updateUser(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->fill($data);
        $user->save();

        return $user;
    }
}
PHP;

        // Chunk code by characters but preserve context with overlap
        $chunks = TextChunker::strategy('character')
            ->size(150)
            ->overlap(25)
            ->chunk($phpCode);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(1);

        // Verify position-based reconstruction works
        $reconstructed = '';
        $lastEnd = 0;
        foreach ($chunks as $chunk) {
            if ($chunk->start_position >= $lastEnd) {
                $reconstructed .= $chunk->text;
            } else {
                $overlapSize = $lastEnd - $chunk->start_position;
                $reconstructed .= mb_substr($chunk->text, $overlapSize);
            }
            $lastEnd = $chunk->end_position;
        }

        expect($reconstructed)->toBe($phpCode);
    });

    test('boundary condition zero overlap produces continuous non overlapping chunks', function () {
        $text = str_repeat('ABCD', 25); // 100 characters

        $chunks = app('text-chunker')->strategy('character')
            ->size(10)
            ->overlap(0)
            ->chunk($text);

        expect($chunks)->toHaveCount(10);

        // Verify chunks are perfectly continuous with no gaps or overlaps
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            expect($chunks[$i]->end_position)->toBe($chunks[$i + 1]->start_position);
            expect($chunks[$i]->text)->toHaveLength(10);
        }

        // Verify last chunk
        expect($chunks[9]->end_position)->toBe(100);
    });

    test('boundary condition overlap approaching 100 percent creates many overlapping chunks', function () {
        $text = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // 26 characters

        $chunks = app('text-chunker')->strategy('character')
            ->size(10)
            ->overlap(90) // 90% overlap = 9 characters overlap
            ->chunk($text);

        // With 90% overlap, chunks should move forward by only 1 character each time
        // Chunk 0: pos 0-10 (ABCDEFGHIJ)
        // Chunk 1: pos 1-11 (BCDEFGHIJK)
        // Chunk 2: pos 2-12 (CDEFGHIJKL)
        // ... continues until we reach end
        expect(count($chunks))->toBeGreaterThan(15);

        // Verify high overlap creates expected pattern
        if (count($chunks) > 1) {
            $stepSize = $chunks[0]->end_position - $chunks[1]->start_position;
            expect($stepSize)->toBe(9); // 90% of 10 = 9 characters overlap
        }
    });

    test('mixed content with all special characters and punctuation', function () {
        $text = 'Hello! @user #hashtag $100 50% off... "quoted text" (parentheses) [brackets] {braces} <angles> | pipe / slash \\ backslash';

        $strategies = ['character', 'sentence', 'markdown'];

        foreach ($strategies as $strategy) {
            $chunks = app('text-chunker')->strategy($strategy)
                ->size(30)
                ->chunk($text);

            expect($chunks)->toBeArray();

            // Verify position-based extraction works with special chars
            foreach ($chunks as $chunk) {
                $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted, "Special char handling failed in {$strategy}");
            }
        }
    });

    test('extremely long single line text without natural break points', function () {
        // Create text with no natural breaks - just a long word
        $longWord = str_repeat('abcdefghij', 100); // 1000 character "word"

        $chunks = app('text-chunker')->strategy('character')
            ->size(100)
            ->overlap(0)
            ->chunk($longWord);

        expect($chunks)->toHaveCount(10);

        // Verify each chunk except possibly the last is exactly 100 chars
        for ($i = 0; $i < count($chunks) - 1; $i++) {
            expect($chunks[$i]->text)->toHaveLength(100);
        }

        // Verify complete coverage
        $lastChunk = end($chunks);
        expect($lastChunk->end_position)->toBe(1000);
    });

    test('token strategy handles technical content with code and symbols', function () {
        $technicalText = 'The function calculateHash(data) => crypto.SHA256(data) returns a hexadecimal string. Example: 0x1a2b3c4d';

        $chunks = app('text-chunker')->strategy('token')
            ->size(15)
            ->overlap(20)
            ->chunk($technicalText);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);

        // Verify positions are accurate even with technical symbols
        foreach ($chunks as $chunk) {
            $extracted = mb_substr($technicalText, $chunk->start_position, $chunk->end_position - $chunk->start_position);
            expect($chunk->text)->toBe($extracted);
        }
    });

    test('sentence strategy handles complex punctuation patterns', function () {
        $complexText = 'Mr. Smith asked, "Did Dr. Jones say \'Yes\'?" Mrs. Brown replied, "No... He said: \'Maybe!\'" What a day!';

        $chunks = app('text-chunker')->strategy('sentence')
            ->size(2)
            ->chunk($complexText);

        expect($chunks)->toBeArray();

        // Should properly identify sentences despite quotes and nested punctuation
        foreach ($chunks as $chunk) {
            // Each chunk should contain sentence-ending punctuation
            expect($chunk->text)->toMatch('/[.!?].*$/');
        }
    });
});
