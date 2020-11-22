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

namespace mod_adastra\local\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that can be used as a callable (lambda) in Mustache templates:
 * convert a Unix timestamp (integer) to a date string.
 * Pass an instance of this class to the Mustache context.
 * In the template, you must supply one argument to the callable: the timestamp.
 *
 * Example:
 * Preparing context variables before rendering:
 * $context->todatestr = new \mod_adastra\local\helper\date_to_string();
 * $context->timestamp = time();
 *
 * In the Mustache template:
 * {{# todatestr }}{{ timestamp }}{{/ todatestr }}
 */
class date_to_string {

    protected $format;

    /**
     * Create a new instance.
     *
     * @param string $format Format string to the PHP date function.
     */
    public function __construct($format = 'r') {
        $this->format = $format;
    }

    public function __invoke($timestamp, $mustachehelper) {
        // The timestamp must be rendered to get the integer, otherwise it is a string like '{{ date }}'.
        return \date($this->format, $mustachehelper->render($timestamp));
    }
}