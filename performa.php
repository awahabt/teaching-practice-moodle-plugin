<?php
/**
 * performa.php
 *
 * Displays and processes the evaluation form for CT and HT.
 *
 * URL parameters:
 *   id        = course module ID
 *   studentid = student being evaluated
 *   role      = 'ct' or 'ht'
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teachingpractice/lib.php');
require_once($CFG->dirroot . '/mod/teachingpractice/classes/form/performa_form.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
if (!$cmid) {
    $cmid = required_param('id', PARAM_INT);
}
$studentid = required_param('studentid', PARAM_INT);
$role      = required_param('role',      PARAM_ALPHA);

if (!in_array($role, ['ct', 'ht'])) {
    throw new moodle_exception('invalidparameter', 'error');
}

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'teachingpractice');
$instance = $DB->get_record('teachingpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check correct capability for the role
if ($role === 'ct') {
    require_capability('mod/teachingpractice:fillct', $context);
} else {
    require_capability('mod/teachingpractice:fillht', $context);
}

$student  = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

// Auto-sync Section A from Moodle (profile + linked assignment submission).
if (tp_has_submitted_project($studentid, $instance->linked_assign)) {
    $performa = tp_sync_student_performa($instance, $studentid);
} else {
    $performa = tp_get_performa($instance->id, $studentid);
}

$PAGE->set_url(new moodle_url('/mod/teachingpractice/performa.php', [
    'id'        => $cmid,
    'studentid' => $studentid,
    'role'      => $role,
]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$title = ($role === 'ct') ? 'Cooperating Teacher Evaluation' : 'Head Teacher Evaluation';
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname . ' — ' . $title);

// Guard: HT cannot submit before CT
if ($role === 'ht') {
    if (!$performa || $performa->status === TP_STATUS_PENDING) {
        \core\notification::error(
            'Head Teacher evaluation cannot be submitted until the Cooperating Teacher has completed their evaluation first.'
        );
        redirect(new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]));
    }
    if ($performa->status === TP_STATUS_COMPLETED) {
        \core\notification::info('Evaluation already completed. The certificate has been issued.');
        redirect(new moodle_url('/mod/teachingpractice/certificate.php', [
            'id'        => $cmid,
            'studentid' => $studentid,
        ]));
    }
}

// Guard: CT cannot resubmit
if ($role === 'ct' && $performa && $performa->status !== TP_STATUS_PENDING) {
    \core\notification::info('You have already submitted the Cooperating Teacher evaluation for this student.');
    redirect(new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]));
}

// Guard: student must have submitted project before CT can evaluate
if ($role === 'ct' && !tp_has_submitted_project($studentid, $instance->linked_assign)) {
    \core\notification::error(
        get_string('msg_project_not_submitted_ct', 'mod_teachingpractice')
    );
    redirect(new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]));
}

$sectiondata = tp_build_section_a_data($student, $performa, $instance);
$certno = null;
if ($performa && $performa->status === TP_STATUS_COMPLETED) {
    $certrecord = $DB->get_record('teachingpractice_certificate', ['performaid' => $performa->id]);
    if ($certrecord) {
        $certno = $certrecord->certificate_no;
    }
}

// Initialise form
$form = new performa_form(null, [
    'role'        => $role,
    'performa'    => $performa,
    'student'     => $student,
    'cmid'        => $cmid,
    'instance'    => $instance,
    'sectiondata' => $sectiondata,
    'certno'      => $certno,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]));
}

if ($data = $form->get_data()) {

    if ($role === 'ct') {

        if (!$performa) {
            throw new moodle_exception('error_no_performa', 'mod_teachingpractice');
        }

        // Update existing record — Section A stays as submitted by the student.
        $performa->ct_subject_knowledge      = $data->ct_subject_knowledge;
        $performa->ct_lesson_planning        = $data->ct_lesson_planning;
        $performa->ct_instructional_delivery = $data->ct_instructional_delivery;
        $performa->ct_classroom_management   = $data->ct_classroom_management;
        $performa->ct_assessment_feedback    = $data->ct_assessment_feedback;
        $performa->ct_professionalism        = $data->ct_professionalism;
        $performa->ct_remarks                = $data->ct_remarks ?? '';
        $performa->ct_teacher_name           = $data->ct_teacher_name;
        $performa->ct_submitted_date         = time();
        $performa->status                    = TP_STATUS_CT_DONE;
        $performa->timemodified              = time();
        $DB->update_record('teachingpractice_performa', $performa);

        \core\notification::success(
            get_string('msg_performa_saved', 'mod_teachingpractice')
        );
        redirect(new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]));

    } elseif ($role === 'ht') {

        $performa->ht_attendance                 = $data->ht_attendance;
        $performa->ht_punctuality                = $data->ht_punctuality;
        $performa->ht_participation_teaching     = $data->ht_participation_teaching;
        $performa->ht_participation_cocurr       = $data->ht_participation_cocurricular;
        $performa->ht_professional_conduct       = $data->ht_professional_conduct;
        $performa->ht_overall_recommendation     = $data->ht_overall_recommendation;
        $performa->ht_remarks                    = $data->ht_remarks ?? '';
        $performa->ht_teacher_name               = $data->ht_teacher_name;
        $performa->ht_submitted_date             = time();
        $performa->timemodified                  = time();
        $DB->update_record('teachingpractice_performa', $performa);

        // Issue certificate — also marks course complete for student
        $cert_no = tp_issue_certificate($performa, $instance->course);

        if ($cert_no) {
            \core\notification::success(
                get_string('msg_certificate_issued', 'mod_teachingpractice') .
                ' Certificate No: ' . $cert_no
            );
        } else {
            \core\notification::info('Evaluation submitted. Certificate was already issued previously.');
        }

        redirect(new moodle_url('/mod/teachingpractice/certificate.php', [
            'id'        => $cmid,
            'studentid' => $studentid,
        ]));
    }
}

// Render page
echo $OUTPUT->header();

// Info bar showing which student is being evaluated
echo html_writer::div(
    html_writer::tag('strong', 'Evaluating: ') . fullname($student) .
    html_writer::tag('span',
        '&nbsp;|&nbsp;Role: ' . ($role === 'ct' ? 'Cooperating Teacher' : 'Head Teacher'),
        ['class' => 'text-muted']
    ),
    'alert alert-secondary mb-3'
);

$form->display();
echo $OUTPUT->footer();
