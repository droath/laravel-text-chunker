# Changelog

All notable changes to `laravel-text-chunker` will be documented in this file.

## v1.0.0 - 2025-11-15

Initial release of Laravel Text Chunker - a flexible, strategy-based text chunking package for Laravel applications.

### Features

#### Core Architecture
- **Strategy Pattern**: Implemented flexible strategy-based architecture for text chunking
- **Fluent API**: Chainable method calls for intuitive usage (`strategy()->size()->overlap()->chunk()`)
- **Immutable Chunks**: Readonly value objects with text, index, and position metadata
- **Lazy Validation**: Validation deferred to execution time for better developer experience
- **Laravel Integration**: Service provider, facade, and auto-discovery support

#### Built-in Strategies
- **Character Strategy**: Split text at exact character count boundaries with multibyte UTF-8 support
- **Token Strategy**: Split text by OpenAI token count using tiktoken library for optimal API usage
- **Sentence Strategy**: Split text at sentence boundaries with configurable abbreviation handling
- **Markdown Strategy**: Preserve markdown structure (code blocks, headers, lists, blockquotes, horizontal rules) while chunking

#### Advanced Capabilities
- **Overlap Support**: Percentage-based overlap (0-100%) for context preservation across chunks
- **Custom Strategies**: Easy registration of custom chunking strategies via interface implementation
- **Position Tracking**: Accurate character position tracking (start_position, end_position) for all chunks
- **Configurable Options**: Strategy-specific options (token model selection, custom abbreviations, etc.)

#### Developer Experience
- **Comprehensive Documentation**: Full PHPDoc coverage for all public APIs
- **Descriptive Exceptions**: Clear, actionable error messages with available options listed
- **Type Safety**: Strict types throughout with PHP 8.3+ modern syntax
- **Extensive Testing**: 103 tests with 522 assertions covering all functionality
- **Code Quality**: PSR-12 compliant via Laravel Pint, PHPStan level 5 static analysis

### Configuration
- Publishable configuration file for default strategy and strategy-specific settings
- Auto-registration of custom strategies from config
- Token model configuration (gpt-4, gpt-3.5-turbo, etc.)
- Sentence abbreviations configuration (Dr., Mr., Mrs., Ms., etc.)

### Requirements
- PHP 8.3 or higher
- Laravel 10.x or 11.x

### Dependencies
- `yethee/tiktoken ^0.12.0` - OpenAI token encoding/decoding
- `spatie/laravel-package-tools ^1.16` - Package bootstrapping utilities

### Testing
- Complete test coverage with Pest 4.x framework
- Unit tests for all strategies, manager, and core components
- Feature tests for end-to-end workflows and integration
- Validation tests for all error conditions
- Strategic gap tests for edge cases and real-world scenarios

### Notes
- This is the MVP release focused on core chunking functionality
- All public APIs are considered stable
- Future releases will maintain backward compatibility
