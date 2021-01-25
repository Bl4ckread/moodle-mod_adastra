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

require_once(__DIR__ . '/backup_adastra_stepslib.php');

class backup_adastra_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     *
     * @return void
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     *
     * @return void
     */
    protected function define_my_steps() {
        // Ad Astra only has one structure step.
        $this->add_step(new backup_adastra_activity_structure_step('adastra_structure', 'adastra.xml'));
    }

    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of exercise rounds.
        $search = "/(" . $base . "\/mod\/" . mod_adastra\local\data\exercise_round::TABLE . "\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADASTRAINDEX*$2@$', $content);

        // Link to round view by moduleid.
        $search = "/(" . $base . "\/mod\/" . mod_adastra\local\data\exercise_round::TABLE . "\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADASTRAVIEWBYID*$2@$', $content);

        // Link to round view by ad astra id.
        $search = "/(" . $base . "\/mod\/" . mod_adastra\local\data\exercise_round::TABLE . "\/view.php\?s\=)([0-9]+)/";
        $content = preg_replace($search, '$@ADASTRAVIEWBYS*@2@$', $content);

        return $content;
    }
}