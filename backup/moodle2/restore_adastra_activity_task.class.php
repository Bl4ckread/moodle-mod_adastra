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

class restore_adastra_activity_task extends restore_activity_task {

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
        $this->add_step(new restore_adastra_activity_structure_step('adastra_structure', 'adastra.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array An empty array.
     */
    public static function define_decode_contents() {
        $contents = array();
        // We do not expect any plugin content (e.g. descriptions in rounds) to include links to
        // other plugin pages in Moodle, so that we would need to decode them in restoring to update
        // new IDs in the links.

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule(
                'ADASTRAINDEX',
                '/mod/' . mod_adastra\local\data\exercise_round::TABLE . '/index.php?id=$1',
                'course'
        );
        $rules[] = new restore_decore_rule(
                'ADASTRAVIEWBYID',
                '/mod/' . mod_adastra\local\data\exercise_round::TABLE . '/view.php?id=$1',
                'course_module'
        );
        $rules[] = new restore_decode_rule(
                'ADASTRAVIEWBYS',
                '/mod/' . mod_adastra\local\data\exercise_round::TABLE . '/view.php?s=$1', 'adastra'
        );

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the {@link restore_logs_processor}
     * when restoring Ad Astra logs. It must return one array of {@link restore_log_rule} objects.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule(
                mod_adastra\local\data\exercise_round::TABLE,
                'view',
                'view.php?id={course_module}',
                '{adastra}'
        );
        $rules[] = new restore_log_rule(
                mod_adastra\local\data\exercise_round::TABLE,
                'view exercise',
                'exercise.php?id={learningobject}',
                '{learningobject}'
        );
        $rules[] = new restore_log_rule(
                mod_adastra\local\data\exercise_round::TABLE,
                'submit solution',
                'submission.php?id={submission}',
                '{submission}'
        );

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the {@link restore_logs_processor}
     * when restoring course logs. It must return one array of {@link restore_log_rule} objects.
     * Note these rules are applied when restoring course logs by the restore final task, but
     * are defined here at activity level. All of them are rules not linked to any module instance (cmid = 0).
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule(
                mod_adastra\local\data\exercise_round::TABLE,
                'view all',
                'index.php?id={course}',
                null
        );

        return $rules;
    }
}