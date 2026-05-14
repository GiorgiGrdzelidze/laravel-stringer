<?php

declare(strict_types=1);

namespace Stringer\Laravel\Services;

/**
 * Strips common "AI tell" phrases from generated prose.
 *
 * The draft prompt forbids a list of giveaway phrases ("delve", "leverage",
 * "in today's fast-paced world", …) but LLMs cheat — they swap to another
 * unmistakable opener like "It's worth noting that…" or "Furthermore,".
 * This service applies a deterministic post-pass to clean those up.
 *
 * Conservative by design: matches are case-insensitive, anchored at common
 * sentence/clause boundaries, and replace with the empty string (then we
 * normalise whitespace). False positives are preferred over false negatives —
 * the rules only target phrases that almost never serve real human writing.
 *
 * Applied to:
 *  - `TranslatableString` / `TranslatableMarkdown` values (per locale)
 *  - `String` / `Markdown` values
 * Skipped for tags, categories, integers, code blocks (preserved intact).
 */
final class AiTellSanitizer
{
    /**
     * Phrases to strip. Each entry is matched as a case-insensitive regex
     * anchored either at start-of-line, after a period+space, or at a
     * paragraph boundary. The captured group is replaced with the empty
     * string; whitespace is normalised after.
     *
     * @var list<string>
     */
    private const PHRASES = [
        // Throat-clearing openers.
        "it's worth noting that",
        'it is worth noting that',
        "it's important to note that",
        'it is important to note that',
        'it should be noted that',
        'note that',
        'importantly,',
        'notably,',
        'crucially,',
        'fundamentally,',
        'essentially,',
        'basically,',
        'simply put,',
        'in essence,',
        'in a nutshell,',

        // Transition fillers.
        'furthermore,',
        'moreover,',
        'additionally,',
        'in addition,',
        'on top of that,',
        'that being said,',
        'with that said,',
        'having said that,',

        // Conclusion clichés.
        'in conclusion,',
        'to sum up,',
        'to summarise,',
        'to summarize,',
        'in summary,',
        'all in all,',
        'all things considered,',
        'at the end of the day,',
        'when all is said and done,',

        // Marketing / hype clichés.
        "in today's fast-paced world,",
        'in this fast-paced world,',
        "in today's digital age,",
        'in this digital age,',
        'in the modern world,',
        'in this modern era,',
        "let's dive in",
        "let's dive into",
        "let's get started",
        'without further ado,',
        'when it comes to',
    ];

    public function sanitize(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        [$body, $codeBlocks] = $this->extractCodeBlocks($text);

        foreach (self::PHRASES as $phrase) {
            // Match the phrase at the start of a line / paragraph or right
            // after a sentence-ending punctuation followed by whitespace.
            $pattern = '/(^|[.!?]\s+|\n+)'.preg_quote($phrase, '/').'\s*/imu';
            $body = (string) preg_replace_callback(
                $pattern,
                static fn (array $m): string => $m[1],
                $body,
            );
        }

        // Capitalise the next sentence after a strip if a lowercase letter
        // now follows the sentence boundary (e.g. "X. additionally, foo" →
        // "X. foo" → still lowercase 'foo').
        $body = (string) preg_replace_callback(
            '/(^|[.!?]\s+|\n\n+)([a-zà-ÿ])/u',
            static fn (array $m): string => $m[1].mb_strtoupper($m[2]),
            $body,
        );

        // Collapse the double-spaces our regex may have left behind, but
        // preserve paragraph breaks.
        $body = (string) preg_replace('/[ \t]{2,}/u', ' ', $body);
        $body = (string) preg_replace('/ +\n/u', "\n", $body);

        return $this->restoreCodeBlocks($body, $codeBlocks);
    }

    /**
     * Pull fenced code blocks and inline code spans out so we never touch
     * code samples. Returns the placeholder-substituted body + the captured
     * blocks indexed by placeholder.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function extractCodeBlocks(string $text): array
    {
        $captured = [];
        $index = 0;

        $text = (string) preg_replace_callback(
            '/```[\s\S]*?```/u',
            static function (array $m) use (&$captured, &$index): string {
                $placeholder = "\0STRINGER_CODE_BLOCK_{$index}\0";
                $captured[$placeholder] = $m[0];
                $index++;

                return $placeholder;
            },
            $text,
        );

        $text = (string) preg_replace_callback(
            '/`[^`\n]+`/u',
            static function (array $m) use (&$captured, &$index): string {
                $placeholder = "\0STRINGER_INLINE_CODE_{$index}\0";
                $captured[$placeholder] = $m[0];
                $index++;

                return $placeholder;
            },
            $text,
        );

        return [$text, $captured];
    }

    /**
     * @param  array<string, string>  $captured
     */
    private function restoreCodeBlocks(string $text, array $captured): string
    {
        if ($captured === []) {
            return $text;
        }

        return strtr($text, $captured);
    }
}
