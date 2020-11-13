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

    // Create new table adastra_categories.
    if ($oldversion < 2020111301) {

        // Define table adastra_categories to be created.
        $table = new xmldb_table('adastra_categories');

        // Adding fields to table adastra_categories.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '35', null, XMLDB_NOTNULL, null, null);
        $table->add_field('pointstopass', XMLDB_TYPE_INTEGER, '7', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table adastra_categories.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table adastra_categories.
        $table->add_index('course', XMLDB_INDEX_NOTUNIQUE, ['course']);

        // Conditionally launch create table for adastra_categories.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adastra savepoint reached.
        upgrade_mod_savepoint(true, 2020111301, 'adastra');
    }

    // Create new table adastra_lobjects.
    if ($oldversion < 2020111302) {

        // Define table adastra_lobjects to be created.
        $table = new xmldb_table('adastra_lobjects');

        // Adding fields to table adastra_lobjects.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('roundid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('ordernum', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('remotekey', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('serviceurl', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usewidecolumn', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table adastra_lobjects.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'adastra_categories', ['id']);
        $table->add_key('roundid', XMLDB_KEY_FOREIGN, ['roundid'], 'adastra', ['id']);
        $table->add_key('parentid', XMLDB_KEY_FOREIGN, ['parentid'], 'adastra_lobjects', ['id']);

        // Adding indexes to table adastra_lobjects.
        $table->add_index('remotekey', XMLDB_INDEX_NOTUNIQUE, ['remotekey']);

        // Conditionally launch create table for adastra_lobjects.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adastra savepoint reached.
        upgrade_mod_savepoint(true, 2020111302, 'adastra');
    }

    // Create new table adastra_exercises.
    if ($oldversion < 2020111303) {

        // Define table adastra_exercises to be created.
        $table = new xmldb_table('adastra_exercises');

        // Adding fields to table adastra_exercises.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('lobjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxsubmissions', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '10');
        $table->add_field('pointstopass', XMLDB_TYPE_INTEGER, '7', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maxpoints', XMLDB_TYPE_INTEGER, '7', null, XMLDB_NOTNULL, null, '100');
        $table->add_field('gradeitemnumber', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxsbmssize', XMLDB_TYPE_INTEGER, '9', null, XMLDB_NOTNULL, null, '1048576');
        $table->add_field('allowastviewing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('allowastgrading', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table adastra_exercises.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('lobjectid', XMLDB_KEY_FOREIGN, ['lobjectid'], 'adastra_lobjects', ['id']);

        // Conditionally launch create table for adastra_exercises.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adastra savepoint reached.
        upgrade_mod_savepoint(true, 2020111303, 'adastra');
    }

    // Create table adastra_chapters.
    if ($oldversion < 2020111304) {

        // Define table adastra_chapters to be created.
        $table = new xmldb_table('adastra_chapters');

        // Adding fields to table adastra_chapters.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('lobjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('generatetoc', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table adastra_chapters.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('lobjectid', XMLDB_KEY_FOREIGN, ['lobjectid'], 'adastra_lobjects', ['id']);

        // Conditionally launch create table for adastra_chapters.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Adastra savepoint reached.
        upgrade_mod_savepoint(true, 2020111304, 'adastra');
    }

    // For further information please read the Upgrade API documentation:
    // https://docs.moodle.org/dev/Upgrade_API
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at:
    // https://docs.moodle.org/dev/XMLDB_editor.

    return true;
}
