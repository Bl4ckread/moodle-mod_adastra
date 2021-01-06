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

/**
 * Plugin administration pages are defined here.
 *
 * @package     mod_adastra
 * @category    admin
 * @copyright   2020 Your Name <you@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Secret key is used to generate keyed hash values for the asynchronous exercise service API.
    $settings->add(new admin_setting_configtext(
            mod_adastra\local\data\exercise_round::MODNAME . '/secretkey', // Name.
            get_string('asyncsecretkey', mod_adastra\local\data\exercise_round::MODNAME), // Visible name.
            get_string('asyncsecretkey_help', mod_adastra\local\data\exercise_round::MODNAME), // Description.
            \mod_adastra\local\data\submission::get_random_string(100, true), // Default value, generate a new random key.
            '/^[[:ascii:]]{50,100}$/', // Validation: regular expression, ASCII characters, 50-100 chars long.
            80 // Size of the text field.
    ));

    // Curl CA certificate locations, used in HTTPS connections to the exercise service.
    $settings->add(new admin_setting_heading(
            mod_adastra\local\data\exercise_round::MODNAME . '/curl_ca_heading', // Name (not really used for a heading).
            get_string('cacertheading', mod_adastra\local\data\exercise_round::MODNAME), // Heading.
            get_string('explaincacert', mod_adastra\local\data\exercise_round::MODNAME) // Information/description.
    ));

    // CAINFO: a single CA certificate bundle (absolute path to the file).
    $settings->add(new admin_setting_configtext(
            mod_adastra\local\data\exercise_round::MODNAME . '/curl_cainfo', // Name (key for the setting).
            get_string('cainfopath', mod_adastra\local\data\exercise_round::MODNAME), // Visible name.
            get_string('cainfopath_help', mod_adastra\local\data\exercise_round::MODNAME), // Description.
            null, // Default value.
            PARAM_RAW, // Parameter type for validation, raw: no cleaning besides removing invalid utf-8 characters.
            50 // Size of the text field.
    ));

    // CAPATH: directory that contains CA certificates (file names hashed like OpenSSL expects).
    $settings->add(new admin_setting_configtext(
            mod_adastra\local\data\exercise_round::MODNAME . '/curl_capath', // Name (key for the setting).
            get_string('cadirpath', mod_adastra\local\data\exercise_round::MODNAME), // Visible name.
            get_string('cadirpath_help', mod_adastra\local\data\exercise_round::MODNAME), // Description.
            null, // Default value.
            PARAM_RAW, // parameter type for validation, raw: no cleaning besides removing invalid utf-8 characters.
            50 // Size of the text field.
    ));
}
