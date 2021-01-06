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

/**
 * Event class that represents an error in the connection to the external exercise
 * service server, for example, when curl cannot connect to the server.
 *
 * An event is created like this:
 * $event = \mod_adastra\event\service_connection_failed::create(array(
 *     'context' => context_module::instance($cm->id),
 *     'relateduserid' => $user->id, // optional user that is related to the action,
 *     // may be different than the user taking the action
 *     'other' => array(
 *         'error' => curl_error($ch),
 *         'url' => 'https://tried.to.connect.here.com',
 *         'objtable' => 'adastra_lobjects', // or 'adastra_submissions', used if relevant
 *         'objid' => 1, // id of the module instance (DB row), zero means none
 *     )
 * ));
 * $event->trigger();
 */
class service_connection_failed extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'r'; // Create = 'c', read = 'r', update = 'u', delete = 'd'.
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        // Do not set objecttable here so that we can use the event for many types
        // (exercise rounds, exercises etc.).
    }

    /**
     * Return localised name of the event, it is the same for all instances.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventserviceconnectionfailed', \mod_adastra\local\data\exercise_round::MODNAME);
    }

    /**
     * Return non-localised description of one particular event.
     *
     * @return string
     */
    public function get_description() {
        $url = isset($this->other['url']) ? $this->other['url'] : '';
        $error = isset($this->other['error']) ? $this->other['error'] : '';
        return 'Error in connecting to URL "' . $url . '" (' . $error . ').';
    }

    /**
     * Return Moodle URL where the event can be observed afterwards.
     * Can be null, if no valid location is present.
     *
     * @return \moodle_url|null
     */
    public function get_url() {
        if (!isset($this->other['objtable']) || !isset($this->other['objid']) || $this->other['objid'] == 0) {
            return null;
        }
        if ($this->other['objtable'] == \mod_adastra\local\data\learning_object::TABLE) {
            return new \moodle_url(
                    '/mod/' . \mod_adastra\local\data\exercise_round::TABLE . '/exercise.php',
                    array('id' => $this->other['objid']) // Ad Astra learning object id.
            );
        }
        if ($this->other['objtable'] == \mod_adastra\local\data\submission::TABLE) {
            return new \moodle_url(
                    '/mod/' . \mod_adastra\local\data\exercise_round::TABLE . '/submission.php',
                    array('id' => $this->other['objid']) // Ad Astra submission id.
            );
        }
        return null;
    }

    public static function get_other_mapping() {
        // Cannot map objid in other data for backup/restore since this method is static
        // and the objtable varies.
        return false;
    }
}