<?php

declare(strict_types=1);

namespace Droath\TextChunker\Concerns;

/**
 * HasOverlap provides percentage-based overlap calculation for chunking
 * strategies.
 *
 * This trait enables strategies to add overlapping content between chunks
 * for maintaining context across chunk boundaries. The overlap is specified
 * as a percentage (0-100) and converted to units appropriate for each
 * strategy.
 */
trait HasOverlap
{
    /**
     * The overlap percentage (0-100) or null if no overlap is set.
     */
    protected ?int $overlapPercentage = null;

    /**
     * Set the overlap percentage for chunk boundaries.
     *
     * @param int $percentage The overlap percentage (0-100)
     */
    public function setOverlap(int $percentage): self
    {
        $this->overlapPercentage = $percentage;

        return $this;
    }

    /**
     * Check if overlap is configured.
     *
     * @return bool True if the overlap percentage is set, false otherwise
     */
    public function hasOverlap(): bool
    {
        return $this->overlapPercentage !== null;
    }

    /**
     * Calculate the overlap amount in units based on chunk size.
     *
     * Converts the percentage to actual units by multiplying the chunk size
     * by the percentage. For example, 50% overlap on a 100-unit chunk = 50
     * units.
     *
     * @param int $chunkSize The size of the chunk in strategy-specific units
     *
     * @return int The number of units to overlap (0 if no overlap set)
     */
    public function calculateOverlapAmount(int $chunkSize): int
    {
        if (! $this->hasOverlap()) {
            return 0;
        }

        return (int) round($chunkSize * ($this->overlapPercentage / 100));
    }
}
