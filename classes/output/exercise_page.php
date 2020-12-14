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



namespace mod_adastra\output;

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/../../locallib.php');

class exercise_page implements \renderable, \templatable {

    protected $exround;
    protected $learningobject;
    protected $exercisesummary; // If the learning object is an exercise.
    protected $user;
    protected $errormsg;
    protected $pagerequires;

    /**
     * Construct a new exercise_page instance.
     *
     * @param \mod_adastra\local\data\exercise_round $exround
     * @param \mod_adastra\local\data\learning_object $learningobject
     * @param \stdClass $user
     * @param \page_requirements_manager $pagerequires
     * @param string|null $errormsg
     */
    public function __construct(
            \mod_adastra\local\data\exercise_round $exround,
            \mod_adastra\local\data\learning_object $learningobject,
            \stdClass $user,
            \page_requirements_manager $pagerequires,
            $errormsg = null
    ) {
        $this->exround = $exround;
        $this->learningobject = $learningobject;
        $this->user  = $user;
        $this->pagerequires = $pagerequires; // From $PAGE->requires.
        if ($learningobject->is_submittable()) {
            $this->exercisesummary = new \mod_adastra\local\summary\user_exercise_summary($learningobject, $user);
        } else {
            $this->exercisesummary = null;
        }
        $this->errormsg = $errormsg;
    }

    /**
     * Copy CSS and JS requirements from the remote page head (with data-aplus attributes)
     * to the Moodle page. Likewise, JS inline scripts with data-astra-jquery attribute
     * are copied from anywhere in the remote page, and they are automatically embedded
     * in AMD code that loads the jQuery JS library.
     *
     * @param \mod_adastra\local\protocol\exercise_page $page
     * @return void
     */
    protected function set_moodle_page_requirements(\mod_adastra\local\protocol\exercise_page $page) {
        foreach ($page->injectedcssurls as $cssurl) {
            // Absolute (external) URL must be passed as moodle_url instance.
            $this->pagerequires->css(new \moodle_url($cssurl));
        }

        list($jsurls, $jsinlinecode) = $page->injectedjsurlsandinline;
        foreach ($jsurls as $jsurl) {
            // Absolute (external) URL must be passed as moodle_url instance.
            $this->pagerequires->js(new \moodle_url($jsurl));
        }
        foreach ($jsinlinecode as $inlinecode) {
            // The code probably is not using any AMD modules but the Moodle page API
            // does not have other methods to inject inline JS code to the page.
            $this->pagerequires->js_amd_inline($inlinecode);
        }

        // Inline scripts (JS code inside <script>) with jQuery.
        foreach ($page->inlinejqueryscripts as $scriptelem) {
            // Import jQuery in the Moodle way, jQuery module is visible to the code in the given name $scriptelem[1].
            $js = 'require(["jquery"], function(' . $scriptelem[1] . ') { ' . $scriptelem[0] . ' });';
            $this->pagerequires->js_amd_inline($js);
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();
        $ctx = \context_module::instance($this->exround->get_course_module()->id);
        $data->iscoursestaff = \has_capability('mod/adastra:viewallsubmissions', $ctx);
        $data->iseditingteacher = \has_capability('mod/adastra:addinstance', $ctx);
        if ($this->learningobject->is_submittable()) {
            $data->caninspect = $this->learningobject->is_assistant_viewing_allowed() && $data->iscoursestaff ||
                    $data->iseditingteacher;
        } else {
            $data->caninspect = $data->iscoursestaff;
        }

        $data->statusmaintenance = $this->exround->is_under_maintenance() || $this->learningobject->is_under_maintenance();
        $data->notstarted = !$this->exround->has_started();

        if (!($data->statusmaintenance || $data->notstarted) || $data->iscoursestaff) {
            try {
                $page = $this->learningobject->load($this->user->id);
                $this->set_moodle_page_requirements($page);
                $page->content = adastra_filter_exercise_content($page->content, $ctx);
                $data->page = $page->get_template_context(); // Has content field.
            } catch (\mod_adastra\protocol\remote_page_exception $e) {
                $data->error = \get_string('serviceconnectionfailed', \mod_adastra\local\data\exercise_round::MODNAME);
                $page = new \stdClass();
                $page->content = '';
                $data->page = $page;
            }
        }

        if (!is_null($this->errormsg)) {
            if (isset($data->error)) {
                $data->error .= '<br>' . $this->errormsg;
            } else {
                $data->error = $this->errormsg;
            }
        }

        $data->indexurl = \mod_adastra\local\urls\urls::rounds_index($this->exround->get_course_module()->course);
        if ($this->learningobject->is_submittable()) {
            $data->exercise = $this->learningobject->get_exercise_template_context($this->user, true, true, true);
            $data->submissions = $this->learningobject->get_submissions_template_context($this->user->id);
            $data->submission = false;
            $data->summary = $this->exercisesummary->get_template_context();
        } else {
            $data->exercise = $this->learningobject->get_template_context(false, true);
            if ($this->learningobject->should_generate_table_of_contents()) {
                $data->roundtoc = \mod_adastra\output\exercise_round_page::get_round_table_of_contents_context($this->exround);
            } else {
                $data->roundtoc = false;
            }
        }

        $data->todatestr = new \mod_adastra\local\helper\date_to_string();

        return $data;
        // It should return an stdClass with properties that are only made of simple types:
        // int, string, bool, float, stdClass or arrays of these types.
    }
}