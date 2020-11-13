<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Picks the selected language's value from |lang:value|lang:value| format text.
 * Adapted from A+ (a-plus/lib/localization_syntax.py)
 *
 * @param string $text
 * @param null|string $preferredlang The language to use. If null, the current language is used.
 * @param boolean $includeall If true, all values in $text are returned in an array indexed by the lang codes.
 * If $text is a string without any multilang values, it is returned directly.
 * @return string|array
 */
function adastra_parse_localization(string $text, string $preferredlang = null, bool $includeall = false) {
    if (strpos($text, '|') !== false) {
        $currentlang = $preferredlang ?? current_language();
        $variants = explode('|', $text);
        $exercisenumber = $variants[0];
        $langs = array();
        foreach ($variants as $variant) {
            $parts = explode(':', $variant);
            if (count($parts) !== 2) {
                continue;
            }
            list($lang, $val) = $parts;
            $langs[$lang] = $val;

            if (!$includeall && $lang === $currentlang) {
                return $exercisenumber . $val;
            }
        }

        if ($includeall) {
            return $langs;
        } else if (isset($langs['en'])) {
            return $exercisenumber . $langs['en'];
        } else if (!empty($langs)) {
            return $exercisenumber . reset($langs);
        }

        return $exercisenumber;
    }
    return $text;
}

/**
 * Pick the value for the current language from the given text that
 * may contain HTML span elements in the format of the Moodle multilang filter.
 * (<span lang="en" class="multilang">English value</span>)
 *
 * @param string $text
 * @param null|string $preferredlang The language to use. If null, the current language is used.
 * @return string The parsed text.
 */
function adastra_parse_multilang_filter_localization(string $text, string $preferredlang = null) : string {
    $offset = 0;
    $pos = stripos($text, '<span', $offset);
    if ($pos === false) {
        // No multilang values.
        return $text;
    }
    $start = substr($text, 0, $pos); // Substring preceding any multilang spans.
    $currentlang = $preferredlang ?? current_language();

    $pattern = '/<span(?:\s+lang="(?P<lang>[a-zA-Z0-9_-]+)"|\s+class="multilang"){2}\s*>(?P<value>[^<]*)<\/span>/i';
    $langs = array();

    while ($pos !== false) {
        $offset = $pos;
        $matches = array();
        if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $lang = $matches['lang'][0];
            $value = $matches['value'][0];
            if ($lang === $currentlang) {
                return $start . $value;
            }
            $langs[$lang] = $value;

            // Move offset over the span.
            $offset = $matches[0][1] + strlen($matches[0][0]);
        }

        // Find the next span.
        $pos = stripos($text, '<span', $offset);
    }

    if (isset($langs['en'])) {
        return $start . $langs['en'];
    } else if (!empty($langs)) {
        return $start . reset($langs);
    }
    return $text;
}