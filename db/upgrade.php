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

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     mod_adastra
 * @category    upgrade
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/upgradelib.php');

/**
 * Execute mod_adastra upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_adastra_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Create new table adastra_course_settings.
    if ($oldversion < 2020111300) {

        // Define table adastra_course_settings to be created.
        $table = new xmldb_table('adastra_course_settings');

        // Adding fields to table adastra_course_settings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('apikey', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('configurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('sectionnum', XMLDB_TYPE_INTEGER, '2', null, null, null, null);
        $table->add_field('modulenumbering', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('contentnumbering', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('lang', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'en');

        // Adding keys to table adastra_course_settings.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table adastra_course_settings.
        $table->add_index('course', XMLDB_INDEX_UNIQUE, ['course']);

        // Conditionally launch create table for adastra_course_settings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adastra savepoint reached.
        upgrade_mod_savepoint(true, 2020111300, 'adastra');
    }

    // For further information please read the Upgrade API documentation:
    // https://docs.moodle.org/dev/Upgrade_API
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at:
    // https://docs.moodle.org/dev/XMLDB_editor.

    return true;
}
