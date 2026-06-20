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

// Check plugin is fully configured
if (empty($instance->linked_course) || empty($instance->linked_assign) ||
    empty($instance->student_role)  || empty($instance->ct_role) || empty($instance->ht_role)) {

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

        $has_submitted = tp_has_submitted_project($USER->id, $instance->linked_assign);

        if (!$has_submitted) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading($instance->name, 2);
            echo html_writer::div(
                html_writer::tag('h4', 'Project Not Submitted Yet') .
                html_writer::tag('p',
                    get_string('msg_project_not_submitted', 'mod_teachingpractice')
                ) .
                html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $instance->linked_course]),
                    '→ Go to the project submission course',
                    ['class' => 'btn btn-primary mt-2']
                ),
                'alert alert-warning'
            );
            echo $OUTPUT->footer();
            exit;
        }

        $performa = tp_sync_student_performa($instance, $USER->id);

        // Redirect before any output — certificate is ready.
        if ($performa && $performa->status === TP_STATUS_COMPLETED) {
            redirect(new moodle_url('/mod/teachingpractice/certificate.php', [
                'id'        => $id,
                'studentid' => $USER->id,
            ]));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading($instance->name, 2);

        $ct_done = $performa && in_array($performa->status, [TP_STATUS_CT_DONE, TP_STATUS_COMPLETED]);
        $ht_done = $performa && $performa->status === TP_STATUS_COMPLETED;

        echo html_writer::div(
            html_writer::tag('h4', 'Evaluation In Progress') .
            html_writer::tag('p', get_string('msg_evaluation_inprogress', 'mod_teachingpractice')) .
            html_writer::tag('ul',
                html_writer::tag('li', ($ct_done ? '✅' : '⏳') . ' Cooperating Teacher Evaluation') .
                html_writer::tag('li', ($ht_done ? '✅' : '⏳') . ' Head Teacher Evaluation')
            ),
            'alert alert-info'
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
    foreach ($students as $student) {

        $submitted = tp_has_submitted_project($student->id, $instance->linked_assign);

        if ($submitted) {
            tp_sync_student_performa($instance, $student->id);
        }

        $project_badge = $submitted
            ? html_writer::span('✅ Submitted', 'badge badge-success')
            : html_writer::span('❌ Not Submitted', 'badge badge-danger');

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

        } elseif ($role === 'ct' && $submitted) {
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

        } elseif ($role === 'ct' && !$submitted) {
            $action = html_writer::span('Project not submitted', 'text-muted small');
        }

        $table->data[] = [
            $i++,
            fullname($student),
            $student->idnumber ?: '—',
            $project_badge,
            $ct_badge,
            $ht_badge,
            $action,
        ];
    }

    echo html_writer::table($table);
}
