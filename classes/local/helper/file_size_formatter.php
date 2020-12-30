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

namespace mod_adastra\local\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that can be used as a callable (lambda) in Mustache templates:
 * convert a file size in bytes (integer) to a human-readable string.
 * Pass an instance of this class to the Mustache context.
 * In the template, you must supply one argument to the callable: the file size.
 *
 * Example
 * Preparing context variables before rendering:
 * $context->fileSizeFormatter = new \mod_astra\output\file_size_formatter();
 * $context->filesize = 1024;
 *
 * In the Mustache template:
 * {{# fileSizeFormatter }}{{ filesize }}{{/ fileSizeFormatter }}
 */
class file_size_formatter {

    public function __construct() {

    }

    public function __invoke($filesize, $mustachehelper) {
        return self::human_readable_bytes($mustachehelper->render($filesize));
    }

    public static function human_readable_bytes($bytes, $decimals = 2) {
        // Modified from source: http://php.net/manual/en/function.filesize.php#106569 user notes by rommel at rommelsantor dot com.
        $sz = 'BKMGTP';
        $factor = (int) floor((strlen("{$bytes}") - 1) / 3);
        if ($factor > 5) {
            $factor = 5; // Index of $sz out of bounds.
        }
        $suffix = '';
        if ($factor > 0) {
            $suffix = 'B'; // B after the kilo/mega/...
        }
        return sprintf("%.{$decimals}f ", $bytes / pow(1024, $factor)) . $sz[$factor] . $suffix;
    }
}