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

namespace mod_adastra\event;

defined('MOODLE_INTERNAL') || die();

class solution_submitted extends \core\event\base {

    /**
     * Initialize the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c'; // Create = 'c', read = 'r', update = 'u', delete = 'd'.
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = \mod_adastra\local\data\submission::TABLE; // DB table.
    }

    /**
     * Return a localised name of the event, it is the same for all instances.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmitted', \mod_adastra\local\data\exercise_round::MODNAME);
    }

    /**
     * Returns a non-localised description of one particular event.
     *
     * @return string
     */
    public function get_description() {
        return "The user with the id '{$this->userid}' submitted a new solution (id = {$this->objectid}) to " .
                "Ad Astra exercise. Round course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Returns a Moodle URL where the event can be observed afterwards.
     * Can be null, if no valid location is present.
     *
     * @return \moodle_url|null
     */
    public function get_url() {
        return new \moodle_url('/mod/' . \mod_adastra\local\data\exercise_round::TABLE . '/submission.php', array(
                'id' => $this->objectid
        ));
    }

    /**
     * Return object id mapping.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return array('db' => \mod_adastra\local\data\submission::TABLE, 'restore' => 'submission');
    }
}