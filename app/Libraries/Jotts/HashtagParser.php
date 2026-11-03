<?php

namespace App\Libraries\Jotts;

use Normalizer;

/**
 * Finds hashtags in text. A tag is # followed by Unicode letters, numbers or
 * underscores, up to 100 characters. It must not follow a word character or URL
 * punctuation, so C# and https://example.com/#section aren't tags.
 */
final class HashtagParser
{
    private const MAX_LENGTH = 100;
    private const PATTERN    = '/(?<![\p{L}\p{N}_#\/&=?.:%+~-])#([\p{L}\p{M}\p{N}_]+)/u';

    /**
     * @return array<string, string> Display spelling by normalized key, one entry per
     *                               distinct tag. The first spelling in the text wins.
     */
    public function parse(string $text): array
    {
        if (preg_match_all(self::PATTERN, $text, $matches) === false) {
            return [];
        }

        $tags = [];

        foreach ($matches[1] as $display) {
            $key = $this->normalize($display);

            if (mb_strlen($key) <= self::MAX_LENGTH && ! isset($tags[$key])) {
                $tags[$key] = $display;
            }
        }

        return $tags;
    }

    public function normalize(string $tag): string
    {
        $composed = Normalizer::normalize($tag, Normalizer::FORM_C);

        return mb_convert_case($composed === false ? $tag : $composed, MB_CASE_FOLD);
    }
}
