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
 * English language strings for local_coursecohortinfo.
 *
 * @package    local_coursecohortinfo
 * @copyright  2026 Efika / Dalton Gent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Course cohort enrolment info';
$string['coursecohortinfo:view'] = 'View which cohorts are used as enrolment methods in a course';
$string['coursecohortinfo:overridecompletion'] = 'Override a student\'s completion status without being enrolled yourself';
$string['coursecohortinfo:sendnotification'] = 'Send system notifications to students';
$string['completionnotenabledforactivity'] = 'Completion tracking is not enabled for this specific activity';
$string['usernotallowed'] = 'This user was not found, not enrolled, or not allowed access to this activity: {$a}';
$string['messageprovider:deadlinereminder'] = 'Deadline/assignment reminders (LTI integration)';
$string['webhookurl'] = 'Webhook URL';
$string['webhookurl_desc'] = 'The URL a notification is sent to whenever a watched activity (see below) is edited and saved. Leave empty to fully disable the webhook.';
$string['webhooksecret'] = 'Shared signing secret';
$string['webhooksecret_desc'] = 'Used to sign every webhook call (HMAC-SHA256, X-Moodle-Signature header) so the receiving application can verify the notification genuinely came from this Moodle environment. Leave empty to send unsigned (not recommended).';
$string['webhookmodules'] = 'Watched module types';
$string['webhookmodules_desc'] = 'Comma-separated list of module types (e.g. "lti" or "lti,assign") that trigger a webhook when edited. Only activities of these types trigger the webhook.';
$string['webhookfailed'] = 'Sending the webhook failed: {$a}';
