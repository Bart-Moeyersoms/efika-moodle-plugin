<?php
// This file is part of Moodle - http://moodle.org/
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
 * Admin settings for local_coursecohortinfo.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_coursecohortinfo',
        get_string('pluginname', 'local_coursecohortinfo')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_coursecohortinfo/webhookurl',
        get_string('webhookurl', 'local_coursecohortinfo'),
        get_string('webhookurl_desc', 'local_coursecohortinfo'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_coursecohortinfo/webhooksecret',
        get_string('webhooksecret', 'local_coursecohortinfo'),
        get_string('webhooksecret_desc', 'local_coursecohortinfo'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursecohortinfo/webhookmodules',
        get_string('webhookmodules', 'local_coursecohortinfo'),
        get_string('webhookmodules_desc', 'local_coursecohortinfo'),
        'lti',
        PARAM_TEXT
    ));
}
