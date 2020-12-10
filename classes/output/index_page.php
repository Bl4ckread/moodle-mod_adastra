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

defined('MOODLE_INTERNAL') || die();

class index_page implements \renderable, \templatable {

    protected $course;
    protected $rounds;
    protected $coursesummary;

    public function __construct(\stdClass $course, \stdClass $user) {
        $this->course = $course;
        $this->coursesummary = new \mod_adastra\local\summary\user_course_summary($course, $user);
        $this->rounds = $this->coursesummary->get_exercise_rounds();
    }

    public function export_for_template(\renderer_base $output) {
        $data = new \stdClass();
        $ctx = \context_course::instance($this->course->id);
        $data->is_course_staff = has_capability('mod/adastra:viewallsubmissions', $ctx);
        $iseditingteacher = has_capability('mod/adastra:addinstance', $ctx);
        $roundsdata = array();
        foreach ($this->rounds as $round) {
            $roundctx = new \stdClass();
            $roundctx->coursemodule = $round->get_template_context();
            $modulesummary = $this->coursesummary->get_module_summary($round->get_id());
            $roundctx->modulesummary = $modulesummary->get_template_context();
            $roundctx->modulesummary->classes = 'float-right'; // CSS classes.
            $roundctx->modulecontents = $modulesummary->get_module_points_panel_template_context(
                false,
                !$iseditingteacher
            );
            $roundsdata[] = $roundctx;
        }
        $data->rounds = $roundsdata;

        $categories = array();
        foreach ($this->coursesummary->get_category_summaries() as $catsummary) {
            $cat = new \stdClass();
            $cat->name = $catsummary->get_category()->get_name();
            $cat->summary = $catsummary->get_template_context();
            $cat->statusready = ($catsummary->get_category()->get_status() === \mod_adastra\local\data\category::STATUS_READY);
            $categories[] = $cat;
        }
        $data->categories = $categories;

        $data->todatestr = new \mod_adastra\local\helper\date_to_string();

        $data->toc = $this->get_course_table_of_contents_context();

        return $data;
    }

    /**
     * Return table of contents context for the course.
     *
     * @return \stdClass
     */
    protected function get_course_table_of_contents_context() {
        global $DB;

        // Remove rounds with status UNLISTED from the table of contents,
        // hidden rounds should be already removed from $this->rounds.
        $rounds = \array_filter($this->rounds, function($round) {
            return $round->get_status() !== \mod_adastra\local\data\exercise_round::STATUS_UNLISTED;
        });

        $toc = new \stdClass(); // Table of contents.
        $toc->exerciserounds = array();
        foreach ($rounds as $exround) {
            $roundctx = $exround->get_template_context();
            $modulesummary = $this->coursesummary->get_module_summary($exround->get_id());
            $roundctx->lobjects = self::build_round_lobjects_context_for_toc($modulesummary->get_learning_objects());
            $toc->exerciserounds[] = $roundctx;
        }
        return $toc;
    }

    /**
     * Return learning objects template context data for table of contents.
     *
     * @param array $learningobjects
     * @return array
     */
    public static function build_round_lobjects_context_for_toc(array $learningobjects) {
        $lobjectsbyparent = array();
        foreach ($learningobjects as $obj) {
            $parentid = $obj->get_parent_id();
            $parentid = empty($parentid) ? 'top' : $parentid;
            if (!$obj->is_unlisted()) {
                if (!isset($lobjectsbyparent[$parentid])) {
                    $lobjectsbyparent[$parentid] = array();
                }

                $lobjectsbyparent[$parentid][] = $obj;
            }
        }

        // Variable $parentid may be null to get top-level learning objects.
        $children = function($parentid) use ($lobjectsbyparent) {
            $parentid = isset($parentid) ? $parentid : 'top';
            return isset($lobjectsbyparent[$parentid]) ? $lobjectsbyparent[$parentid] : array();
        };

        $traverse = function($parentid) use (&$children, &$traverse) {
            $container = array();
            foreach ($children($parentid) as $child) {
                $childctx = new \stdClass();
                $childctx->isempty = $child->is_empty();
                $childctx->name = $child->get_name();
                $childctx->url = \mod_adastra\local\urls\urls::exercise($child);
                $childctx->children = $traverse($child->get_id());
                $childctx->haschildren = \count($childctx->children) > 0;
                $container[] = $childctx;
            }
            return $container;
        };

        return $traverse(null);
    }
}