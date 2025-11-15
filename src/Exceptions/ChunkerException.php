<?php

declare(strict_types=1);

namespace Droath\TextChunker\Exceptions;

use Exception;

/**
 * ChunkerException is thrown when chunking operations fail.
 *
 * This exception is used for all chunking-related errors, including
 * validation failures, configuration errors, and runtime errors.
 * Use descriptive messages to differentiate between error types.
 */
class ChunkerException extends Exception {}
