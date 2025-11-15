<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Chunking Strategy
    |--------------------------------------------------------------------------
    |
    | This option controls the default chunking strategy used when no strategy
    | is explicitly specified. Available built-in strategies: character, token,
    | sentence, markdown.
    |
    */

    'default_strategy' => env('TEXT_CHUNKER_DEFAULT_STRATEGY', 'character'),

    /*
    |--------------------------------------------------------------------------
    | Strategy-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for individual chunking strategies.
    |
    */

    'strategies' => [
        /*
        | Token Strategy Configuration
        |
        | The token strategy uses the tiktoken library for OpenAI-compatible
        | token encoding. Specify the model to use for tokenization.
        | Supported models: gpt-4, gpt-3.5-turbo, text-davinci-003, etc.
        */
        'token' => [
            'model' => env('TEXT_CHUNKER_TOKEN_MODEL', 'gpt-4'),
        ],

        /*
        | Sentence Strategy Configuration
        |
        | The sentence strategy uses regex-based boundary detection. Configure
        | abbreviations that should not be treated as sentence endings.
        */
        'sentence' => [
            'abbreviations' => [
                'Dr',
                'Mr',
                'Mrs',
                'Ms',
                'Prof',
                'Sr',
                'Jr',
                'etc',
                'e.g',
                'i.e',
                'vs',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Strategy Registration
    |--------------------------------------------------------------------------
    |
    | Register custom chunking strategies that will be automatically available
    | throughout your application. Each key is the strategy identifier and the
    | value is the fully qualified class name implementing ChunkerStrategyInterface.
    |
    | Example:
    | 'custom_strategies' => [
    |     'paragraph' => \App\Chunkers\ParagraphStrategy::class,
    |     'word' => \App\Chunkers\WordStrategy::class,
    | ],
    |
    */

    'custom_strategies' => [
        // Add your custom strategies here
    ],
];
