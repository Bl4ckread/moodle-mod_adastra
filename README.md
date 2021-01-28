# Ad Astra #

This plugin imports task data from Plussa to Moodle environment, so that the tasks can be completed in Moodle course page. Plugin then sends the answers to the grading system, and returns the grades back to Moodle. The plugin works as a updated version for the original Astra plugin, which is no longer viable with the current version of Moodle.

The plugin is made for course admins mainly from Tampere University, for its usage of both Moodle and Plussa. This plugin helps the students by removing some of the need of using both systems.

## Installation ##

At the moment plugin can be only installed manually to the Moodle platform, but the requirement of the finished product will be implementation to the Moodle plugin library.

## Usage ##

Ad Astra will be implemented as two different plugins, one being the actual plugin and the other acting as a simple block to reach plugin properties.
Course admin will add a block to setup Ad Astra. Plussa course url will be added.
Plugin will work separetely with each module implemented from Plussa.
Course student answers to the task and the answer is sent to Plussa grading system for grading. Grade is returned to the Moodle environment, for the student to see.

## Plugin structure

The following list explains how the plugin code is structured and divided:

### amd
Contains the Javascript code for the frontend

### assets
Contains the main css style for the plugin

### backup
Contains the backup API of Moodle

### classes
Contains class definitions, supports Moodle class auto-loading
* cache: contains classes that define caches in the plugin
* event: contains classes that log events in the log
* form: contains forming classes that use Moodle form API
* local: contains classes that work in a local Moodle environment, including auto setup and forming of data
* output: contains classes that output data with output API
* privacy: contains classes that handle user data

### db
Defines parts of the plugin required by Moodle
* access.php: contains plugin capabilities, uses access API
* caches.php: contains area defining for the caches for the Moodle API
* install.php: contains code run at plugin installation process
* install.xml: contains the database schema of the plugin
* uninstall.php: contains the code run at plugin uninstallation
* upgrade.php: contains the code run when the plugin is upgraded, for example when changes are made to the plugin
* upgradelib.php used to group and clear the upgrading code in the upgrade.php

### lang
Contains the strings used in the plugin, currently supports english
### teachers
Contains several php scripts for tasks used by teachers
### templates
Contains mustache-templates, that are a part of the Moodle output API
### tests
Contains PHPunit tests, that are using Moodle test API
* calls.php: contains scripts for function to call API
* courses.php: contains script to get and set the information of the courses using calls functions
* index.php: script to show all the course information from the requested course in the Moodle page
* lib.php: contains all the functions Moodle requires of all the Moodle plugins
* local_settings.php: contains plugins local settings defined with constants
* locallib.php: sets requirements for the moodle page, for example requirement for custom Javascript and CSS
* mod_form.php: contains the form used to create and edit activities
* settings.php: contains the settings to configure the plugin for the administration
* submission.php: contains the code for submission cases of the tasks
* version.php: contains the required information of plugin version number
* view.php: contains the view configuration of the plugin
## Using the plugin
The plugin is set to the Moodle page by using the block plugin part of Ad Astra. The plugin settings are now set, by adding the course url from Plussa etc. Ad astra then runs the calls for the course tasks and runs the auto setup to set the module to Moodle. After the auto setup course data can now be completed in the Moodle platform. The looks of the imported module will match the one seen in Plussa environment, and makes the completion of these tasks similar. Each task includes every aspect of the Plussa completion, for example the points and the number of submissions left.
## Grading
The completed tasks will be sent to the grading system of Plussa, which works independently from Moodle. Working this way Moodle only works as an interface for the call and send functions of the plugin, and requires minimal amount of modification to Moodle itself.
## License ##

2020 Your Name <you@example.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

