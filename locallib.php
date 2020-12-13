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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Filter/format exercise page content so that Moodle filters are activated,
 * e.g. the Moodle MathJax loader renders Latex math formulas.
 * This function may be used to filter exercise descriptions and submission
 * feedbacks that originate from an exercise service.
 *
 * @param string $content (HTML) content to filter.
 * @param contex|int $ctx Moodle context object or context ID of the exercise (round).
 * @return string
 */
function adastra_filter_exercise_content($content, $ctx) {
    return format_text($content, FORMAT_HTML, array(
        'trusted' => true, // Parameter $content is trusted and its dangerous elements are not removed, like <input>.
        'noclean' => true,
        'filter' => true, // Actiave Moodle filters.
        'para' => false, // No extra <div> wrapping.
        'context' => $ctx,
        'allowid' => true, // Retaing HTML element IDs.
    ));
}

/**
 * Convert a number to a roman numeral. Number should be between 0 and 1999.
 * Derived from A+ (a-plus/lib/helpers.py).
 *
 * @param int $number
 * @return string
 */
function adastra_roman_numeral($number) {
    $numbers = array(1000, 900, 500, 400, 100, 90, 50, 40, 10, 9, 5, 4, 1);
    $letters = array('M', 'CM', 'D', 'CD', 'C', 'XC', 'L', 'XL', 'X', 'IX', 'V', 'IV', 'I');
    $roman = '';
    $lennumbers = count($numbers);
    for ($i = 0; $i < $lennumbers; ++$i) {
        while ($number >= $numbers[$i]) {
            $roman .= $letters[$i];
            $number -= $numbers[$i];
        }
    }
    return $roman;
}

/**
 * Add a learning object with its parent objects to the page navbar after the exercise round node.
 *
 * @param moodle_page $page
 * @param int $cmid Moodle course module ID of the exercise round.
 * @param mod_adastra\local\data\learning_object $exercise
 * @return navigation_node The navigation node of the given exercise.
 */
function adastra_navbar_add_exercise(moodle_page $page, $cmid, mod_adastra\local\data\learning_object $exercise) {
    $roundnav = $page->navigation->find($cmid, navigation_node::TYPE_ACTIVITY);

    $parents = array($exercise);
    $ex = $exercise;
    while ($ex = $ex->get_parent_object()) {
        $parents[] = $ex;
    }

    $previousnode = $roundnav;
    // Leaf child comes last in the navbar.
    for ($i = count($parents) -1; $i >= 0; --$i) {
        $previousnode = adastra_navbar_add_one_exercise($previousnode, $parents[$i]);
    }

    return $previousnode;
}

/**
 * Add a single learning object navbar node after the given node.
 *
 * @param navigation_node $previousnode
 * @param mod_adastra\local\data\learning_object $learningobject
 * @return navigation_node
 */
function adastra_navbar_add_one_exercise(navigation_node $previousnode, mod_adastra\local\data\learning_object $learningobject) {
    return $previousnode->add(
            $learningobject->get_name(),
            mod_adastra\local\urls\urls::exercise($learningobject, true, false),
            navigation_node::TYPE_CUSTOM,
            null,
            'ex' . $learningobject->get_id()
    );
}

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
        $currentlang = isset($preferredlang) ? $preferredlang : current_language();
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
    $currentlang = isset($preferredlang) ? $preferredlang : current_language();

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