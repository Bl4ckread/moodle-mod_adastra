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

class exercise_viewed extends \core\event\base {

    /**
     * Initialize the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r'; // Create = 'c', read = 'r', update = 'u', delete = 'd'.
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = \mod_adastra\local\data\learning_object::TABLE; // DB table.
    }

    /**
     * Return localised name ov the event, it is the same for all instances.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventexerciseviewed', \mod_adastra\local\data\exercise_round::MODNAME);
    }

    /**
     * Return non-localised description of one particular event.
     *
     * @return string
     */
    public static function get_description() {
        return "The user with the id '{$this->userid}' viewed an Ad Astra exercise (id = {$this->objectid}).";
    }

    /**
     * Return Moodle URL where the event can be observed afterwards.
     * Can be null, if no valid location is present.
     *
     * @return \moodle_url|null
     */
    public function get_url() {
        return new \moodle_url('/mod/' . \mod_adastra\local\data\exercise_round::TABLE . '/exercise.php', array(
                'id' => $this->objectid,
        ));
    }

    /**
     * Return object id mapping for the event.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return array('db' => \mod_adastra\local\data\learning_object::TABLE, 'restore' => 'learningobject');
    }
}