# Laravel Text Chunker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/droath/laravel-text-chunker.svg?style=flat-square)](https://packagist.org/packages/droath/laravel-text-chunker)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/droath/laravel-text-chunker/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/droath/laravel-text-chunker/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/droath/laravel-text-chunker/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/droath/laravel-text-chunker/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/droath/laravel-text-chunker.svg?style=flat-square)](https://packagist.org/packages/droath/laravel-text-chunker)

A Laravel package that provides flexible, strategy-based text chunking
capabilities for AI/LLM applications. Split text into smaller segments using
character count, token count, sentence boundaries, or markdown-aware strategies
with a fluent, Laravel-friendly API.

Perfect for:

- Optimizing API calls to LLM providers like OpenAI by chunking text to fit
  token limits
- Implementing RAG (Retrieval-Augmented Generation) systems with context-aware
  chunks
- Preserving markdown structure when splitting documentation or content
- Creating custom text splitting logic for domain-specific needs

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or 12.x

## Installation

Install the package via Composer:

```bash
composer require droath/laravel-text-chunker
```

The package will automatically register itself via Laravel's auto-discovery.

### Configuration

Optionally, publish the configuration file:

```bash
php artisan vendor:publish --tag="text-chunker-config"
```

This will create a `config/text-chunker.php` file where you can customize
default settings:

```php
return [
    // Default strategy to use when none is specified
    'default_strategy' => 'character',

    // Strategy-specific configurations
    'strategies' => [
        'token' => [
            // Default OpenAI model for token encoding
            'model' => 'gpt-4',
        ],
        'sentence' => [
            // Abbreviations that should not trigger sentence breaks
            'abbreviations' => ['Dr', 'Mr', 'Mrs', 'Ms', 'Prof', 'Sr', 'Jr'],
        ],
    ],

    // Register custom strategies here
    'custom_strategies' => [
        // 'my-strategy' => \App\TextChunking\MyCustomStrategy::class,
    ],
];
```

## Basic Usage

### Character-Based Chunking

Split text at exact character count boundaries:

```php
use Droath\TextChunker\Facades\TextChunker;

$text = "Your long text content here...";

$chunks = TextChunker::strategy('character')
    ->size(100)
    ->chunk($text);

foreach ($chunks as $chunk) {
    echo "Chunk {$chunk->index}: {$chunk->text}\n";
    echo "Position: {$chunk->start_position} to {$chunk->end_position}\n";
}
```

### Token-Based Chunking

Split text by OpenAI token count (perfect for API optimization):

```php
use Droath\TextChunker\Facades\TextChunker;

$text = "Your long text content here...";

$chunks = TextChunker::strategy('token')
    ->size(500) // 500 tokens per chunk
    ->chunk($text);

// Use different OpenAI model for encoding
$chunks = TextChunker::strategy('token', ['model' => 'gpt-3.5-turbo'])
    ->size(500)
    ->chunk($text);
```

**Supported Models:**

- `gpt-4`
- `gpt-3.5-turbo`
- `text-davinci-003`
- And other models supported by the tiktoken library

### Sentence-Based Chunking

Split text at sentence boundaries:

```php
use Droath\TextChunker\Facades\TextChunker;

$text = "First sentence. Second sentence. Third sentence.";

$chunks = TextChunker::strategy('sentence')
    ->size(2) // 2 sentences per chunk
    ->chunk($text);

// Custom abbreviations
$chunks = TextChunker::strategy('sentence', [
        'abbreviations' => ['Dr', 'Mr', 'Mrs', 'Ph.D']
    ])
    ->size(3)
    ->chunk($text);
```

### Markdown-Aware Chunking

Preserve markdown structure when chunking:

````php
use Droath\TextChunker\Facades\TextChunker;

$markdown = <<<'MD'
# Heading 1

Some content here.

```php
function example() {
    return "code block";
}
```

- List item 1
- List item 2
MD;

$chunks = TextChunker::strategy('markdown')
    ->size(100) // Target size in characters
    ->chunk($markdown);

// Markdown elements (code blocks, headers, lists, blockquotes, horizontal rules)
// are never split in the middle, even if they exceed the chunk size
````

## Advanced Features

### Overlap for Context Preservation

Add percentage-based overlap between chunks to maintain context (ideal for RAG
systems):

```php
use Droath\TextChunker\Facades\TextChunker;

$text = "Your long text content here...";

$chunks = TextChunker::strategy('character')
    ->size(100)
    ->overlap(20) // 20% overlap between chunks
    ->chunk($text);

// Each chunk will include 20% of the previous chunk's content
```

Overlap works with all strategies:

- **Character strategy**: 20% of characters overlap
- **Token strategy**: 20% of tokens overlap
- **Sentence strategy**: 20% of sentences overlap (rounded)
- **Markdown strategy**: 20% overlap while preserving element boundaries

### Chunk Value Objects

Each chunk is returned as an immutable value object with metadata:

```php
$chunks = TextChunker::strategy('character')->size(100)->chunk($text);

foreach ($chunks as $chunk) {
    $chunk->text;             // The chunk text content
    $chunk->index;            // Zero-based index (0, 1, 2, ...)
    $chunk->start_position;   // Character offset in original text (inclusive)
    $chunk->end_position;     // Character offset in original text (exclusive)
}
```

### Using the Manager Directly

Instead of the facade, you can inject the manager:

```php
use Droath\TextChunker\TextChunkerManager;

class MyService
{
    public function __construct(
        protected TextChunkerManager $chunker
    ) {}

    public function processText(string $text): array
    {
        return $this->chunker
            ->strategy('token')
            ->size(500)
            ->overlap(10)
            ->chunk($text);
    }
}
```

## Custom Strategies

Create your own chunking strategies by implementing the
`ChunkerStrategyInterface`:

### Step 1: Create Strategy Class

```php
<?php

declare(strict_types=1);

namespace App\TextChunking;

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Concerns\HasOverlap;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

class WordStrategy implements ChunkerStrategyInterface
{
    use HasOverlap; // Optional: for overlap support

    public function chunk(string $text, int $size, array $options): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $index = 0;
        $position = 0;

        foreach (array_chunk($words, $size) as $wordChunk) {
            $chunkText = implode(' ', $wordChunk);
            $chunkLength = mb_strlen($chunkText);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index++,
                start_position: $position,
                end_position: $position + $chunkLength
            );

            $position += $chunkLength + 1; // +1 for space
        }

        return $chunks;
    }
}
```

### Step 2: Register Strategy

**Option A: Via Configuration**

Add to `config/text-chunker.php`:

```php
return [
    'custom_strategies' => [
        'word' => \App\TextChunking\WordStrategy::class,
    ],
];
```

**Option B: At Runtime**

```php
use Droath\TextChunker\Facades\TextChunker;
use App\TextChunking\WordStrategy;

TextChunker::extend('word', WordStrategy::class);

$chunks = TextChunker::strategy('word')->size(50)->chunk($text);
```

**Option C: In a Service Provider**

```php
use Droath\TextChunker\TextChunkerManager;
use App\TextChunking\WordStrategy;

public function boot(TextChunkerManager $chunker): void
{
    $chunker->extend('word', WordStrategy::class);
}
```

## Fluent API Reference

The package provides a fluent, chainable API:

```php
TextChunker::strategy(string $name, array $options = [])  // Select strategy
    ->size(int $size)                                      // Set chunk size
    ->overlap(int $percentage)                             // Set overlap (0-100)
    ->chunk(string $text)                                  // Execute and return chunks
```

**Method Details:**

- **`strategy(string $name, array $options = [])`**: Select chunking strategy
    - Built-in strategies: `'character'`, `'token'`, `'sentence'`, `'markdown'`
    - Options vary by strategy (e.g., `['model' => 'gpt-4']` for token strategy)

- **`size(int $size)`**: Set target chunk size (required)
    - Interpretation depends on strategy (characters, tokens, sentences)
    - Must be greater than zero

- **`overlap(int $percentage)`**: Set overlap between chunks (optional)
    - Percentage: 0-100
    - Copies content from end of previous chunk to start of next chunk

- **`chunk(string $text)`**: Execute chunking and return array of Chunk objects
    - Validates all parameters (deferred validation)
    - Throws `ChunkerException` on validation failures
    - Returns `array<int, Chunk>`

## Validation and Error Handling

All validation is deferred until the `chunk()` method is called:

```php
use Droath\TextChunker\Facades\TextChunker;
use Droath\TextChunker\Exceptions\ChunkerException;

try {
    $chunks = TextChunker::strategy('character')
        ->size(100)
        ->overlap(150) // Invalid: must be 0-100
        ->chunk($text);
} catch (ChunkerException $e) {
    // Handle validation error
    echo $e->getMessage(); // "Overlap percentage must be between 0 and 100"
}
```

**Common Exceptions:**

- Size not set: `"Chunk size must be set before calling chunk()"`
- Size <= 0: `"Chunk size must be greater than zero"`
- Invalid overlap: `"Overlap percentage must be between 0 and 100"`
- Empty text: `"Text cannot be empty"`
- Unknown strategy:
  `"Unknown chunking strategy: xyz. Available strategies: character, token, sentence, markdown"`
- Invalid token model: `"Unsupported model: xyz"`

## Testing

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

## Code Quality

Format code with Laravel Pint:

```bash
composer format
```

Run static analysis with PHPStan:

```bash
composer analyse
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed
recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report
security vulnerabilities.

## Credits

- [Travis Tomka](https://github.com/droath)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more
information.
