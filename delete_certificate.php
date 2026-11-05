<?php
/**
 * delete_certificate.php
 *
 * Lets a CT or HT delete a student's issued certificate (when the admin switch
 * "Allow Delete Certificate" is on). Shows a confirmation page first.
 *
 * URL parameters:
 *   id        = course module ID
 *   studentid = student whose certificate is deleted
 *   role      = 'ct' or 'ht'
 *   confirm   = 1 once the user has confirmed (requires sesskey)
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/researchproject/lib.php');

$cmid      = required_param('id', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);
$role      = required_param('role', PARAM_ALPHA);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

if (!in_array($role, ['ct', 'ht'])) {
    throw new moodle_exception('invalidparameter', 'error');
}

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'researchproject');
$instance = $DB->get_record('researchproject', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if ($role === 'ct') {
    require_capability('mod/researchproject:fillct', $context);
} else {
    require_capability('mod/researchproject:fillht', $context);
}

if (!tp_certificate_delete_allowed()) {
    throw new moodle_exception('error_certificate_delete_disabled', 'mod_researchproject');
}

$viewurl  = new moodle_url('/mod/researchproject/view.php', ['id' => $cmid]);
$performa = tp_get_performa($instance->id, $studentid);
$cert     = $performa ? $DB->get_record('researchproject_certificate', ['performaid' => $performa->id]) : false;

if (!$performa || !$cert) {
    \core\notification::info(get_string('error_no_certificate', 'mod_researchproject'));
    redirect($viewurl);
}

if ($confirm && confirm_sesskey()) {
    tp_delete_certificate($performa, $role);
    \core\notification::success(get_string('msg_certificate_deleted', 'mod_researchproject'));
    redirect($viewurl);
}

$student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/researchproject/delete_certificate.php', [
    'id'        => $cmid,
    'studentid' => $studentid,
    'role'      => $role,
]));
$PAGE->set_context($context);
$PAGE->set_title('Delete Certificate');
$PAGE->set_heading($course->fullname);

$confirmurl = new moodle_url('/mod/researchproject/delete_certificate.php', [
    'id'        => $cmid,
    'studentid' => $studentid,
    'role'      => $role,
    'confirm'   => 1,
    'sesskey'   => sesskey(),
]);

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('confirm_delete_certificate', 'mod_researchproject', (object) [
        'certno'  => s($cert->certificate_no),
        'student' => fullname($student),
    ]),
    new single_button($confirmurl, get_string('delete'), 'post', single_button::BUTTON_DANGER),
    $viewurl
);
echo $OUTPUT->footer();
