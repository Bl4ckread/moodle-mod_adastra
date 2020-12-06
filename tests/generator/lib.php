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

class mod_adastra_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        $defaultsettings = array(
            'name' => 'Round',
            'introformat' => FORMAT_HTML,
            'ordernum' => 1,
            'status' => \mod_adastra\local\data\exercise_round::STATUS_READY,
            'grade' => 0,
            'pointstopass' => 0,
            'openingtime' => time(),
            'closingtime' => time() + 3600 * 24 * 7,
            'latesbmsallowed' => 0,
            'latesbmsdl' => 0,
            'latesbmspenalty' => 0.5,
        );

        foreach ($defaultsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array)$options);
    }
}