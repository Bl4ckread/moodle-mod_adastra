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

namespace mod_adastra\local\protocol;

defined('MOODLE_INTERNAL') || die();

/**
 * Remote page has not been modified since the given timestamp.
 *
 * Derived from A+ (a-plus/lib/remote_page.py).
 */
class remote_page_not_modified extends \Exception {

    protected $expires;

    public function __construct($expires = 0, $message = null, $code = null, $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->expires = $expires;
    }

    public function expires() {
        return $this->expires;
    }

    public function set_expires($expires) {
        $this->expires = $expires;
    }
}