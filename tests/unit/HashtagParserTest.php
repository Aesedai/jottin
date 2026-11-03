<?php

namespace Tests\Unit;

use App\Libraries\Jotts\HashtagParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HashtagParserTest extends TestCase
{
    /** @return array<string, array{string, list<string>}> */
    public static function cases(): array
    {
        return [
            'anywhere in text'     => ['#start middle #mid end #last', ['start', 'mid', 'last']],
            'case-insensitive key' => ['#Ideas and #ideas and #IDEAS', ['ideas']],
            'punctuation after'    => ['See #one, #two. (#three)!', ['one', 'two', 'three']],
            'underscore, numbers'  => ['#q3_plan #2026', ['q3_plan', '2026']],
            'bare hash'            => ['a # b #', []],
            'double hash'          => ['##nope', []],
            'URL fragment'         => ['https://example.com/page#section', []],
            'URL slash fragment'   => ['https://example.com/#section', []],
            'query then fragment'  => ['https://example.com/?a=1#section', []],
            'not after a word'     => ['Learning C# and F#sharp', []],
            'unicode letters'      => ['#café #日本語 #Ñandú', ['café', '日本語', 'ñandú']],
            'accent stays distinct' => ['#cafe #café', ['cafe', 'café']],
            'no tags'              => ['just words', []],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('cases')]
    public function testFindsTheExpectedKeys(string $text, array $expected): void
    {
        $this->assertSame($expected, array_map('strval', array_keys((new HashtagParser())->parse($text))));
    }

    public function testFirstSpellingIsKeptForDisplay(): void
    {
        $this->assertSame(['interview' => 'Interview'], (new HashtagParser())->parse('#Interview #interview'));
    }

    public function testComposedAndDecomposedAccentsAreTheSameTag(): void
    {
        $composed   = "#caf\u{00E9}";
        $decomposed = "#cafe\u{0301}";

        $this->assertCount(1, (new HashtagParser())->parse("{$composed} {$decomposed}"));
    }

    public function testTagsOverTheLengthLimitAreIgnored(): void
    {
        $parser = new HashtagParser();

        $this->assertCount(1, $parser->parse('#' . str_repeat('a', 100)));
        $this->assertSame([], $parser->parse('#' . str_repeat('a', 101)));
    }
}
