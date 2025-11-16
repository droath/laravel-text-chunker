<?php

declare(strict_types=1);

namespace Droath\TextChunker;

use Droath\TextChunker\Strategies\TokenStrategy;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Strategies\MarkdownStrategy;
use Droath\TextChunker\Strategies\SentenceStrategy;
use Droath\TextChunker\Strategies\CharacterStrategy;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

/**
 * TextChunkerManager provides a fluent API for text chunking with a strategy
 * pattern.
 *
 * This manager orchestrates text chunking by registering strategies, building
 * configuration through a fluent interface, and delegating chunking operations
 * to the selected strategy. Validation is deferred until chunk() execution.
 *
 * @example
 * $manager = new TextChunkerManager();
 * $chunks =
 *     $manager->strategy('character')->size(100)->overlap(10)->chunk($text);
 */
class TextChunkerManager
{
    /**
     * Map of strategy names to strategy class names.
     *
     * @var array<string, class-string<ChunkerStrategyInterface>>
     */
    protected array $strategies = [];

    /**
     * The currently selected strategy name.
     */
    protected ?string $selectedStrategy = null;

    /**
     * The chunk size to use.
     */
    protected ?int $chunkSize = null;

    /**
     * The overlap percentage (0-100) or null if not set.
     */
    protected ?int $overlapPercentage = null;

    /**
     * Strategy-specific options.
     *
     * @var array<string, mixed>
     */
    protected array $strategyOptions = [];

    /**
     * Create a new TextChunkerManager instance and register built-in
     * strategies.
     */
    public function __construct()
    {
        $this->registerBuiltInStrategies();
    }

    /**
     * Register a custom chunking strategy.
     *
     * @param string $name The strategy identifier
     * @param class-string $class The strategy class name
     *
     * @throws ChunkerException If the class does not implement
     *     ChunkerStrategyInterface
     */
    public function extend(string $name, string $class): void
    {
        if (! in_array(ChunkerStrategyInterface::class, class_implements($class) ?: [], true)) {
            throw new ChunkerException(
                "Strategy class {$class} must implement ChunkerStrategyInterface"
            );
        }

        $this->strategies[$name] = $class;
    }

    /**
     * Select a chunking strategy with optional configuration.
     *
     * @param string $name The strategy identifier
     * @param array<string, mixed> $options Strategy-specific options
     *
     * @return $this
     */
    public function strategy(string $name, array $options = []): self
    {
        $this->selectedStrategy = $name;
        $this->strategyOptions = $options;

        return $this;
    }

    /**
     * Set the chunk size.
     *
     * @param int $size The target size for each chunk
     *
     * @return $this
     */
    public function size(int $size): self
    {
        $this->chunkSize = $size;

        return $this;
    }

    /**
     * Set the overlap percentage between chunks.
     *
     * @param int $percentage The overlap percentage (0-100)
     *
     * @return $this
     */
    public function overlap(int $percentage): self
    {
        $this->overlapPercentage = $percentage;

        return $this;
    }

    /**
     * Execute the chunking operation with the configured strategy.
     *
     * This method performs all validations and delegates to the selected
     * strategy.
     *
     * @param string $text The text to chunk
     *
     * @return array<int, DataObjects\Chunk> Array of Chunk value objects
     *
     * @throws ChunkerException When validation fails or chunking errors occur
     */
    public function chunk(string $text): array
    {
        $this->validateChunkingParameters($text);

        $strategy = $this->resolveStrategy();

        if ($this->overlapPercentage !== null && method_exists($strategy, 'setOverlap')) {
            $strategy->setOverlap($this->overlapPercentage);
        }

        $result = $strategy->chunk($text, $this->chunkSize, $this->strategyOptions);

        $this->resetState();

        return $result;
    }

    /**
     * Register all built-in chunking strategies.
     */
    protected function registerBuiltInStrategies(): void
    {
        $this->strategies['character'] = CharacterStrategy::class;
        $this->strategies['token'] = TokenStrategy::class;
        $this->strategies['sentence'] = SentenceStrategy::class;
        $this->strategies['markdown'] = MarkdownStrategy::class;
    }

    /**
     * Validate all chunking parameters before execution.
     *
     * @param string $text The text to validate
     *
     * @throws ChunkerException When validation fails
     */
    protected function validateChunkingParameters(string $text): void
    {
        if ($this->chunkSize === null) {
            throw new ChunkerException('Chunk size must be set before calling chunk()');
        }

        if ($this->chunkSize <= 0) {
            throw new ChunkerException('Chunk size must be greater than zero');
        }

        if (
            $this->overlapPercentage !== null
            && ($this->overlapPercentage < 0 || $this->overlapPercentage > 100)
        ) {
            throw new ChunkerException('Overlap percentage must be between 0 and 100');
        }

        if ($text === '') {
            throw new ChunkerException('Text cannot be empty');
        }

        if (
            $this->selectedStrategy === null ||
            ! isset($this->strategies[$this->selectedStrategy])
        ) {
            $availableStrategies = implode(', ', array_keys($this->strategies));
            $strategyName = $this->selectedStrategy ?? 'none';

            throw new ChunkerException(
                "Unknown chunking strategy: {$strategyName}. Available strategies: {$availableStrategies}"
            );
        }
    }

    /**
     * Resolve the selected strategy to an instance.
     *
     * @return ChunkerStrategyInterface The strategy instance
     */
    protected function resolveStrategy(): ChunkerStrategyInterface
    {
        $className = $this->strategies[$this->selectedStrategy];

        return new $className();
    }

    /**
     * Reset the configuration state to prevent leaks between operations.
     */
    protected function resetState(): void
    {
        $this->selectedStrategy = null;
        $this->chunkSize = null;
        $this->overlapPercentage = null;
        $this->strategyOptions = [];
    }
}
