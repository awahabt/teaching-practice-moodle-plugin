<?php
/**
 * view.php
 *
 * Main entry point for the Teaching Practice activity.
 * Moodle redirects here when a user clicks the activity in the course page.
 *
 * This page detects the current user's role and shows the correct interface:
 *   Student  → Certificate view (or "project not submitted" message)
 *   CT       → Cooperating Teacher evaluation dashboard + form
 *   HT       → Head Teacher evaluation dashboard + form
 *   Unknown  → Access denied message
 *
 * URL: /mod/teachingpractice/view.php?id=COURSE_MODULE_ID
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teachingpractice/lib.php');

// id = course module ID (cmid) — standard Moodle parameter
$id = required_param('id', PARAM_INT);

// Load course module, course, and activity instance
list($course, $cm) = get_course_and_cm_from_cmid($id, 'teachingpractice');
$instance = $DB->get_record('teachingpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/teachingpractice:view', $context);

// Mark activity as viewed (for Moodle completion tracking)
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$student_role = get_config('mod_teachingpractice', 'student_role');
$ct_role      = get_config('mod_teachingpractice', 'ct_role');
$ht_role      = get_config('mod_teachingpractice', 'ht_role');

if (empty($student_role) || empty($ct_role) || empty($ht_role)) {

    $PAGE->set_url(new moodle_url('/mod/teachingpractice/view.php', ['id' => $id]));
    $PAGE->set_context($context);
    $PAGE->set_course($course);
    $PAGE->set_title($instance->name);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('msg_not_configured', 'mod_teachingpractice'),
        'warning'
    );
    echo $OUTPUT->footer();
    exit;
}

// Detect the TP role of the current user in this course context
$tp_role = tp_get_user_role($instance, $context);

$PAGE->set_url(new moodle_url('/mod/teachingpractice/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title($instance->name);
$PAGE->set_heading($course->fullname . ' — ' . $instance->name);

// ── Route to correct interface ────────────────────────────────────────────────
switch ($tp_role) {

    // =========================================================================
    // STUDENT VIEW
    // =========================================================================
    case 'student':
        // ── Automatic matching: find the linked course enrolled by this student ────────
        // Uses the current course shortname (e.g. WORKSHOP|9028|G1474|16BH|ODL|2513)
        // to find another course with the same tail (9028|G1474|16BH|ODL|2513)
        // that this student is enrolled in.
        $matching_course = tp_find_matching_course($course->shortname, $course->id, $USER->id);
        $matching_assign = $matching_course ? tp_find_matching_assignment($matching_course->id) : null;

        if (!$matching_course || !$matching_assign) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading($instance->name, 2);
            echo html_writer::div(
                html_writer::tag('h4', 'Course Not Found') .
                html_writer::tag('p',
                    'The linked project submission course could not be found automatically. ' .
                    'Please make sure you are enrolled in the correct course ' .
                    '(the course that shares the same code as this Teaching Practice course). ' .
                    'If the problem persists, contact your administrator.'),
                'alert alert-warning mb-3'
            );
            echo $OUTPUT->footer();
            break;
        }

        $performa = tp_sync_student_performa($instance, $USER->id);

        // Show the student's current status — no submission gate here.
        // The certificate page itself will block access if project is not submitted+graded.
        $ct_done = $performa && in_array($performa->status, [TP_STATUS_CT_DONE, TP_STATUS_COMPLETED]);
        $ht_done = $performa && $performa->status === TP_STATUS_COMPLETED;

        // If certificate is ready AND project is submitted+graded → redirect to certificate.
        if ($ht_done) {
            $matching_assign_check = $matching_assign;
            $proj_submitted_graded = $matching_assign_check
                ? tp_is_assignment_submitted_and_graded($USER->id, $matching_assign_check->id)
                : false;
            if ($proj_submitted_graded) {
                redirect(new moodle_url('/mod/teachingpractice/certificate.php', [
                    'id'        => $id,
                    'studentid' => $USER->id,
                ]));
            }
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading($instance->name, 2);

        // Check submission status for display only
        $submission_rec = $matching_assign ? $DB->get_record('assign_submission', [
            'assignment' => $matching_assign->id,
            'userid'     => $USER->id,
            'latest'     => 1,
        ]) : null;
        $is_submitted = $submission_rec && in_array($submission_rec->status, ['submitted', 'graded']);
        $is_graded    = $matching_assign ? tp_is_assignment_submitted_and_graded($USER->id, $matching_assign->id) : false;

        if (!$is_submitted) {
            echo html_writer::div(
                html_writer::tag('h4', 'Project Not Submitted Yet') .
                html_writer::tag('p', get_string('msg_project_not_submitted', 'mod_teachingpractice')) .
                html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $matching_course->id]),
                    '→ Go to the project submission course: ' . s($matching_course->fullname),
                    ['class' => 'btn btn-primary mt-2']
                ),
                'alert alert-warning mb-3'
            );
        } elseif (!$is_graded) {
            echo html_writer::div(
                html_writer::tag('h4', 'Project Submitted — Awaiting Grade') .
                html_writer::tag('p', 'Your project has been submitted successfully, but it has not been graded yet. The certificate will only be available once it has been graded.'),
                'alert alert-info mb-3'
            );
        }

        // Evaluation progress (always shown)
        echo html_writer::div(
            html_writer::tag('h4', 'Evaluation Status') .
            html_writer::tag('ul',
                html_writer::tag('li', ($ct_done ? '✅' : '⏳') . ' Cooperating Teacher Evaluation') .
                html_writer::tag('li', ($ht_done ? '✅' : '⏳') . ' Head Teacher Evaluation')
            ) .
            ($ht_done && !$is_graded
                ? html_writer::tag('p',
                    '⚠️ Both evaluations are complete. Your certificate will be available once your project is graded.',
                    ['class' => 'mb-0'])
                : ''
            ),
            'alert alert-' . ($ht_done ? 'success' : 'info')
        );

        echo $OUTPUT->footer();
        break;

    // =========================================================================
    // COOPERATING TEACHER VIEW
    // =========================================================================
    case 'ct':
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Cooperating Teacher — Student Evaluations', 2);
        tp_render_teacher_dashboard($id, $instance, $context, 'ct', $OUTPUT, $DB);
        echo $OUTPUT->footer();
        break;

    // =========================================================================
    // HEAD TEACHER VIEW
    // =========================================================================
    case 'ht':
        echo $OUTPUT->header();
        echo $OUTPUT->heading('Head Teacher — Student Evaluations', 2);
        tp_render_teacher_dashboard($id, $instance, $context, 'ht', $OUTPUT, $DB);
        echo $OUTPUT->footer();
        break;

    // =========================================================================
    // UNKNOWN ROLE
    // =========================================================================
    default:
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('error_invalid_role', 'mod_teachingpractice'),
            'error'
        );
        echo $OUTPUT->footer();
        break;
}

// =============================================================================
// HELPER: Teacher dashboard — list of students with status + action buttons
// =============================================================================
function tp_render_teacher_dashboard($cmid, $instance, $context, $role, $OUTPUT, $DB) {

    $students = tp_get_instance_students($instance, $context);

    if (empty($students)) {
        echo $OUTPUT->notification(
            get_string('msg_no_students', 'mod_teachingpractice'), 'info'
        );
        return;
    }

    // Build table
    $table             = new html_table();
    $table->head       = [
        '#', 'Student Name', 'Reg. No.',
        'Project Submitted', 'CT Evaluation', 'HT Evaluation', 'Action'
    ];
    $table->attributes = ['class' => 'table table-bordered table-hover generaltable'];

    $i = 1;

    // ── Resolve the linked course + assignment ONCE for this page (not per student) ──
    // Automatic matching from the TP course shortname.
    $tp_course       = $DB->get_record('course', ['id' => $instance->course]);
    $matching_course = $tp_course ? tp_find_matching_course($tp_course->shortname, $tp_course->id) : null;
    $matching_assign = $matching_course ? tp_find_matching_assignment($matching_course->id) : null;

    foreach ($students as $student) {

        tp_sync_student_performa($instance, $student->id);

        $project_status = 'not_submitted';
        if ($matching_assign) {
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $matching_assign->id,
                'userid'     => $student->id,
                'latest'     => 1,
            ]);
            if ($submission && in_array($submission->status, ['submitted', 'graded'])) {
                $submitted_and_graded = tp_is_assignment_submitted_and_graded($student->id, $matching_assign->id);
                if ($submitted_and_graded) {
                    $project_status = 'graded';
                } else {
                    $project_status = 'submitted';
                }
            }
        }

        if ($project_status === 'graded') {
            $project_badge = html_writer::span('✅ Submitted & Graded', 'badge badge-success');
        } elseif ($project_status === 'submitted') {
            $project_badge = html_writer::span('⏳ Submitted, Not Graded', 'badge badge-warning');
        } else {
            $project_badge = html_writer::span('❌ Not Submitted', 'badge badge-danger');
        }

        $performa  = tp_get_performa($instance->id, $student->id);

        if (!$performa || $performa->status === TP_STATUS_PENDING) {
            $ct_badge = html_writer::span('Pending', 'badge badge-warning');
            $ht_badge = html_writer::span('Pending', 'badge badge-warning');
        } elseif ($performa->status === TP_STATUS_CT_DONE) {
            $ct_badge = html_writer::span('Submitted', 'badge badge-success');
            $ht_badge = html_writer::span('Pending', 'badge badge-warning');
        } else {
            $ct_badge = html_writer::span('Submitted', 'badge badge-success');
            $ht_badge = html_writer::span('Submitted', 'badge badge-success');
        }

        // Determine action button
        $action = '—';

        if ($performa && $performa->status === TP_STATUS_COMPLETED) {
            $cert_url = new moodle_url('/mod/teachingpractice/certificate.php', [
                'id'        => $cmid,
                'studentid' => $student->id,
            ]);
            $action = html_writer::link($cert_url, 'View Certificate',
                ['class' => 'btn btn-sm btn-info']);

        } elseif ($role === 'ct') {
            if (!$performa || $performa->status === TP_STATUS_PENDING) {
                $form_url = new moodle_url('/mod/teachingpractice/performa.php', [
                    'id'        => $cmid,
                    'studentid' => $student->id,
                    'role'      => 'ct',
                ]);
                $action = html_writer::link($form_url, 'Fill CT Evaluation',
                    ['class' => 'btn btn-sm btn-primary']);
            } else {
                $action = html_writer::span('Already Submitted', 'text-muted small');
            }

        } elseif ($role === 'ht') {
            if ($performa && $performa->status === TP_STATUS_CT_DONE) {
                $form_url = new moodle_url('/mod/teachingpractice/performa.php', [
                    'id'        => $cmid,
                    'studentid' => $student->id,
                    'role'      => 'ht',
                ]);
                $action = html_writer::link($form_url, 'Fill HT Evaluation',
                    ['class' => 'btn btn-sm btn-success']);
            } elseif (!$performa || $performa->status === TP_STATUS_PENDING) {
                $action = html_writer::span('Waiting for CT evaluation', 'text-muted small');
            } else {
                $action = html_writer::span('Already Submitted', 'text-muted small');
            }
        }

        $table->data[] = [
            $i++,
            fullname($student),
            $student->username ?: '—',
            $project_badge,
            $ct_badge,
            $ht_badge,
            $action,
        ];
    }

    echo html_writer::table($table);
}
