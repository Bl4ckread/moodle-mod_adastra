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

/**
 * Sort Ad Astra exercise round activities in the given Moodle course section.
 * The exercise rounds are sorted according to Ad Astra settings: smaller ordernum
 * comes first.
 * Non Ad Astra activities are kept before all Ad Astra activities
 *
 * @param int $courseid Moodle course ID.
 * @param int $coursesection Section number (0-N) in the course, sorting
 * affects only the activities in this section.
 * @return void
 */
function adastra_sort_activities_in_section($courseid, $coursesection) {
    global $DB;
    $sectionrow = $DB->get_record(
            'course_sections',
            array(
                    'course' => $courseid,
                    'section' => $coursesection,
            ),
            'id, sequence',
            IGNORE_MISSING
    );
    if ($sectionrow === false) {
        return;
    }
    // Course module records.
    $coursemodinfo = get_fast_modinfo($courseid)->cms; // Indexes are course module ids.
    // Ad Astra exercise round records in the course.
    $adastras = $DB->get_records(\mod_adastra\local\data\exercise_round::TABLE, array('course' => $courseid));

    /*
     * Sorting callback function for sorting an array of course module ids.
     * Only Ad Astra modules allowed in the array.
     * Order: assignment 1 is elss than assignment 2, assignment 1 subassignments follow
     * assignment 1 immediately before assignment 2, in alphabetical order of
     * subassignment names.
     */
    $sortfunc = function($cmid1, $cmid2) use ($coursemodinfo, $adastras) {
        $cm1 = $coursemodinfo[$cmid1];
        $cm2 = $coursemodinfo[$cmid2];
        // Figure out Ad Astra round order numbers.
        $order1 = $adastras[$cm1->instance]->ordernum;
        $order2 = $adastras[$cm2->instance]->ordernum;

        // Must return an integer less than, equal to, or greater than zero if the first argument
        // is considered to be respectively less than, equal to or greater than the second.
        if ($order1 < $order2) {
            return -1;
        } else if ($order2 < $order1) {
            return 1;
        } else { // Same order number.
            if ($cm1->instance < $cm2->instance) { // Compare IDs.
                return -1;
            } else if ($cm1->instance > $cm2->instance) {
                return 1;
            } else {
                return 0;
            }
        }
    };

    $nonadastramodules = array(); // Cm ids.
    $adastramodules = array();
    // Cm ids in the section.
    $sequencestr = trim($sectionrow->sequence);
    if (empty($sequencestr)) {
        $coursemoduleids = array();
    } else {
        $coursemoduleids = explode(',', $sequencestr);
    }
    foreach ($coursemoduleids as $cmid) {
        $cm = $coursemodinfo[$cmid];
        if ($cm->modname == \mod_adastra\local\data\exercise_round::TABLE) {
            $adastramodules[] = $cmid;
        } else {
            $nonadastramodules[] = $cmid;
        }
    }
    usort($adastramodules, $sortfunc); // Sort Ad Astra exercise round activities.
    // Add non Ad Astra modules to the beginning.
    $sectioncmids = array_merge($nonadastramodules, $adastramodules);
    // Write the new section ordering (sequence) to DB.
    $newsectionsequence = implode(',', $sectioncmids);
    $DB->set_field(
            'course_sections',
            'sequence',
            $newsectionsequence,
            array('id' => $sectionrow->id)
    );
}

/**
 * Sort Ad Astra items in the Moodle gradebook corresponding to the hierachical
 * order of the course content.
 *
 * @param int $courseid
 * @return void
 */
function adastra_sort_gradebook_items($courseid) {
    global $CFG;
    require_once($CFG->libdir . '/grade/grade_item.php');

    // Retrieve gradebook grade items in the course.
    $params = array('courseid' => $courseid);
    $gradeitems = grade_item::fetch_all($params);
    // Method fetch_all returns an array of grade_item instances or false if none found.
    if (empty($gradeitems)) {
        return;
    }

    // Retrieve the rounds and exercises of the course in the sorted order.
    // Store them in a different format that helps with sorting the gradeitems.
    $rounds = mod_adastra\local\data\exercise_round::get_exercise_rounds_in_course($courseid, true);
    $courseorder = array();
    foreach ($rounds as $round) {
        $courseorder[$round->get_id()] = $round;
    }

    // Callback functions for sorting the $gradeitems array.
    $compare = function($x, $y) {
        if ($x < $y) {
            return -1;
        } else if ($x > $y) {
            return 1;
        } else {
            return 0;
        }
    };

    $sortfunc = function($a, $b) use ($courseorder, &$compare) {
        if (
                $a->itemtype === 'mod' &&
                $a->itemmodule === mod_adastra\local\data\exercise_round::TABLE &&
                $b->itemtype === 'mod' &&
                $b->itemmodule === mod_adastra\local\data\exercise_round::TABLE
        ) {
            // Both grade items are for Ad Astra.
            // Property $gradeitem->iteminstance is the round id and itemnumber is set by the plugin:
            // zero for rounds and there are no grade items for exercises.
            return $compare(
                    $courseorder[$a->iteminstance]->get_order(),
                    $courseorder[$b->iteminstance]->get_order()
            );
        }
        // At least one grade item originates from outside Ad Astra:
        // Sort them according to the old sort order.
        return $compare($a->sortorder, $b->sortorder);
    };

    // Sort $gradeitems array and then renumber the sortorder fields.
    usort($gradeitems, $sortfunc);
    $sortorder = 1;
    foreach ($gradeitems as $item) {
        $item->set_sortorder($sortorder);
        $sortorder += 1;
    }
}

/**
 * Add edit course page to the navbar.
 *
 * @param moodle_page $page $PAGE
 * @param int $courseid
 * @param bool $active
 * @return navigation_node The new navnode.
 */
function adastra_edit_course_navbar(moodle_page $page, $courseid, $active = true) {
    $coursenav = $page->navigation->find($courseid, navigation_node::TYPE_COURSE);
    $editnav = $coursenav->add(get_string('editcourse', \mod_adastra\local\data\exercise_round::MODNAME),
            \mod_adastra\local\urls\urls::edit_course($courseid, true),
            navigation_node::TYPE_CUSTOM, null, 'editcourse');
    if ($active) {
        $editnav->make_active();
    }
    return $editnav;
}

/**
 * Add both edit course and another page to the navbar.
 *
 * @param moodle_page $page
 * @param int $courseid
 * @param string $title Title for the new page.
 * @param moodle_url $url URL of the new page.
 * @param string $navkey Navbar key for the new page.
 * @return navigation_node
 */
function adastra_edit_course_navbar_add(moodle_page $page, $courseid, $title, moodle_url $url, $navkey) {
    $editcoursenav = adastra_edit_course_navbar($page, $courseid, false);
    $nav = $editcoursenav->add($title, $url, navigation_node::TYPE_CUSTOM, null, $navkey);
    $nav->make_active();
    return $nav;
}