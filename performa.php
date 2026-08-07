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

// Ensure the WORKSHOP course for this specific student is fully linked.
// tp_find_student_workshop_course() requires WORKSHOP prefix, matches
// coursecode + mode + semester on both shortname AND idnumber, and restricts
// to courses the student is actively enrolled in.
$matching_course = tp_find_student_workshop_course($course->shortname, $course->id, $studentid);
$course_fully_linked = false;
if ($matching_course) {
    $parsed = tp_parse_course_shortname($course->shortname);
    $coursecode   = !empty($parsed['coursecode']) ? $parsed['coursecode'] : 'N/A';
    $modecode     = !empty($parsed['modecode']) ? $parsed['modecode'] : 'N/A';
    $semestercode = !empty($parsed['semestercode']) ? $parsed['semestercode'] : 'N/A';

    $m_parsed = tp_parse_course_shortname($matching_course->shortname);
    $m_coursecode   = !empty($m_parsed['coursecode']) ? $m_parsed['coursecode'] : '';
    $m_modecode     = !empty($m_parsed['modecode']) ? $m_parsed['modecode'] : '';
    $m_semestercode = !empty($m_parsed['semestercode']) ? $m_parsed['semestercode'] : '';

    $mode_mismatch = (strval($modecode) !== 'N/A' && $m_modecode !== '' && strcasecmp($modecode, $m_modecode) !== 0);
    $sem_mismatch  = (strval($semestercode) !== 'N/A' && $m_semestercode !== '' && strcasecmp($semestercode, $m_semestercode) !== 0);

    if (!$mode_mismatch && !$sem_mismatch) {
        $course_fully_linked = true;
    }
}

if (!$course_fully_linked) {
    throw new moodle_exception('error_course_not_linked', 'mod_teachingpractice', '', null,
        'Evaluation cannot be performed: no matching WORKSHOP course found for this student (checked shortname + idnumber, mode, and semester).');
}


$student  = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

// Auto-sync Section A from Moodle (profile + linked assignment submission).
$performa = tp_sync_student_performa($instance, $studentid);

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

// Removed student project submission guard for CT evaluation

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

        // Validate all rating fields against the allowed whitelist.
        $valid_ratings = array_keys(tp_get_rating_options());
        $ct_rating_fields = [
            'ct_subject_knowledge', 'ct_lesson_planning', 'ct_instructional_delivery',
            'ct_classroom_management', 'ct_assessment_feedback', 'ct_professionalism',
        ];
        foreach ($ct_rating_fields as $rf) {
            if (!in_array($data->$rf, $valid_ratings, true)) {
                throw new moodle_exception('invalidparameter', 'error',
                    '', null, 'Invalid rating value for field: ' . $rf);
            }
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

        // Validate all HT rating fields against allowed whitelists.
        $valid_ratings = array_keys(tp_get_rating_options());
        $valid_recommendations = array_keys(tp_get_recommendation_options());
        $ht_rating_fields = [
            'ht_attendance', 'ht_punctuality', 'ht_participation_teaching',
            'ht_participation_cocurricular', 'ht_professional_conduct',
        ];
        foreach ($ht_rating_fields as $rf) {
            if (!in_array($data->$rf, $valid_ratings, true)) {
                throw new moodle_exception('invalidparameter', 'error',
                    '', null, 'Invalid rating value for field: ' . $rf);
            }
        }
        if (!in_array($data->ht_overall_recommendation, $valid_recommendations, true)) {
            throw new moodle_exception('invalidparameter', 'error',
                '', null, 'Invalid recommendation value.');
        }

        $performa->ht_attendance                 = $data->ht_attendance;
        $performa->ht_punctuality                = $data->ht_punctuality;
        $performa->ht_participation_teaching     = $data->ht_participation_teaching;
        $performa->ht_participation_cocurr       = $data->ht_participation_cocurricular;
        $performa->ht_professional_conduct       = $data->ht_professional_conduct;
        $performa->ht_overall_recommendation     = $data->ht_overall_recommendation;
        $performa->ht_remarks                    = $data->ht_remarks ?? '';
        $performa->ht_teacher_name               = $data->ht_teacher_name;
        $performa->ht_submitted_date             = time();

        // Save Head Teacher entered date and subjects
        $performa->start_date = (int) $data->ht_start_date;
        $performa->end_date   = (int) $data->ht_end_date;

        if ($performa->start_date && $performa->end_date) {
            $days = round(($performa->end_date - $performa->start_date) / 86400) + 1;
            $performa->days_count = ($days > 0) ? (int)$days : 0;
        }

        $selected_subjects = isset($data->ht_subjects) && is_array($data->ht_subjects) ? array_values($data->ht_subjects) : [];
        if (count($selected_subjects) > 3) {
            $performa->subject_1 = json_encode($selected_subjects);
            $performa->subject_2 = '';
            $performa->subject_3 = '';
        } else {
            $performa->subject_1  = isset($selected_subjects[0]) ? $selected_subjects[0] : '';
            $performa->subject_2  = isset($selected_subjects[1]) ? $selected_subjects[1] : '';
            $performa->subject_3  = isset($selected_subjects[2]) ? $selected_subjects[2] : '';
        }

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
