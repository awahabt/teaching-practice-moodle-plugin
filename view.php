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

        // Compute average grade and pass/fail across all assignments in the linked course.
        $student_avg_pct  = $matching_course
            ? tp_get_student_average_grade_percentage($USER->id, $matching_course->id)
            : null;
        $student_passes   = $matching_course
            ? tp_student_passes_course($USER->id, $matching_course->id)
            : false;

        // If certificate is ready AND student passes → redirect to certificate.
        if ($ht_done && $student_passes) {
            redirect(new moodle_url('/mod/teachingpractice/certificate.php', [
                'id'        => $id,
                'studentid' => $USER->id,
            ]));
        }

        echo $OUTPUT->header();

        // Check submission status across all assignments in the linked course.
        $has_any_submission = false;
        $all_graded         = false;
        if ($matching_course) {
            $all_assigns = $DB->get_records('assign', ['course' => $matching_course->id], 'id ASC');
            foreach ($all_assigns as $a) {
                $sub = $DB->get_record('assign_submission', [
                    'assignment' => $a->id,
                    'userid'     => $USER->id,
                    'latest'     => 1,
                ]);
                if ($sub && in_array($sub->status, ['submitted', 'graded'])) {
                    $has_any_submission = true;
                }
            }
            // "All graded" means we have at least one graded assignment (avg is not null).
            $all_graded = ($student_avg_pct !== null);
        }

        if (!$has_any_submission) {
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
        } elseif (!$all_graded) {
            echo html_writer::div(
                html_writer::tag('h4', 'Project Submitted — Awaiting Grade') .
                html_writer::tag('p', 'Your project has been submitted successfully, but it has not been graded yet. The certificate will only be available once all assignments are graded.'),
                'alert alert-info mb-3'
            );
        } elseif (!$student_passes) {
            // Graded but did not meet the passing mark.
            echo html_writer::div(
                html_writer::tag('h4', '❌ Did Not Meet Passing Criteria') .
                html_writer::tag('p',
                    'Your average mark across all assignments is <strong>' . number_format($student_avg_pct, 1) . '%</strong>. ' .
                    'The minimum passing mark is <strong>' . TP_PASSING_PERCENTAGE . '%</strong>. ' .
                    'Please contact your instructor for further guidance.'
                ),
                'alert alert-danger mb-3'
            );
        } else {
            // Passed but evaluations may still be pending.
            echo html_writer::div(
                html_writer::tag('h4', '✅ Project Passed') .
                html_writer::tag('p',
                    'Your average mark is <strong>' . number_format($student_avg_pct, 1) . '%</strong> ' .
                    '(passing mark: ' . TP_PASSING_PERCENTAGE . '%). Your certificate will be available once both evaluations are complete.'
                ),
                'alert alert-success mb-3'
            );
        }

        // Evaluation progress (always shown)
        echo html_writer::div(
            html_writer::tag('h4', 'Evaluation Status') .
            html_writer::tag('ul',
                html_writer::tag('li', ($ct_done ? '✅' : '⏳') . ' Cooperating Teacher Evaluation') .
                html_writer::tag('li', ($ht_done ? '✅' : '⏳') . ' Head Teacher Evaluation')
            ) .
            ($ht_done && !$student_passes
                ? html_writer::tag('p',
                    '⚠️ Both evaluations are complete but the certificate requires a passing grade.',
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
    // UNKNOWN/ADMIN/MANAGER ROLE
    // =========================================================================
    default:
        echo $OUTPUT->header();

        // Check if the user is a manager, administrator, or has course update rights.
        if (has_capability('moodle/course:manageactivities', $context) || is_siteadmin()) {
            echo $OUTPUT->heading('Teaching Practice - Administrator / Manager Dashboard', 2);
            tp_render_teacher_dashboard($id, $instance, $context, 'manager', $OUTPUT, $DB);
        } else {
            echo $OUTPUT->notification(
                get_string('error_invalid_role', 'mod_teachingpractice'),
                'error'
            );
        }

        echo $OUTPUT->footer();
        break;
}
// =============================================================================
// HELPER: Teacher dashboard — list of students with status + action buttons
// =============================================================================
function tp_render_teacher_dashboard($cmid, $instance, $context, $role, $OUTPUT, $DB) {

    // Fetch current course details.
    $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);

    // Fetch and parse the shortname matching details.
    $matching_course = tp_find_matching_course($course->shortname, $course->id);
    $parsed = tp_parse_course_shortname($course->shortname);
    $coursecode   = !empty($parsed['coursecode']) ? $parsed['coursecode'] : 'N/A';
    $modecode     = !empty($parsed['modecode']) ? $parsed['modecode'] : 'N/A';
    $semestercode = !empty($parsed['semestercode']) ? $parsed['semestercode'] : 'N/A';

    $course_fully_linked = false;
    $mode_mismatch = false;
    $sem_mismatch = false;
    $mismatch_details = [];

    if ($matching_course) {
        $m_parsed = tp_parse_course_shortname($matching_course->shortname);
        $m_coursecode   = !empty($m_parsed['coursecode']) ? $m_parsed['coursecode'] : '';
        $m_modecode     = !empty($m_parsed['modecode']) ? $m_parsed['modecode'] : '';
        $m_semestercode = !empty($m_parsed['semestercode']) ? $m_parsed['semestercode'] : '';

        // Determine if there is a mismatch on Mode or Semester.
        $mode_mismatch = (strval($modecode) !== 'N/A' && $m_modecode !== '' && strcasecmp($modecode, $m_modecode) !== 0);
        $sem_mismatch  = (strval($semestercode) !== 'N/A' && $m_semestercode !== '' && strcasecmp($semestercode, $m_semestercode) !== 0);

        if (!$mode_mismatch && !$sem_mismatch) {
            $course_fully_linked = true;
        } else {
            if ($mode_mismatch) {
                $mismatch_details[] = 'Study Mode (Expected: <strong>' . s($modecode) . '</strong>, Found: <strong>' . s($m_modecode) . '</strong>)';
            }
            if ($sem_mismatch) {
                $mismatch_details[] = 'Semester Code (Expected: <strong>' . s($semestercode) . '</strong>, Found: <strong>' . s($m_semestercode) . '</strong>)';
            }
        }
    }

    // Only render matching info card if there is an issue (not linked or mismatch warning).
    if (!$course_fully_linked) {
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header bg-light');
        echo html_writer::tag('h5', 'Linked Project Submission Course Integration', ['class' => 'mb-0']);
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');

        if ($matching_course) {
            echo html_writer::div(
                html_writer::tag('strong', '⚠️ Match Warning: Linked course found with matching Course Code, but Study Mode or Semester does not match!') . '<br>' .
                'Linked Course: ' . html_writer::link(new moodle_url('/course/view.php', ['id' => $matching_course->id]), s($matching_course->fullname) . ' (' . s($matching_course->shortname) . ')', ['target' => '_blank']) . '<br>' .
                'Mismatched fields:<br>' .
                html_writer::tag('ul', implode('', array_map(function($detail) {
                    return html_writer::tag('li', $detail);
                }, $mismatch_details))) .
                'Please align the shortnames so Course Code, Study Mode, and Semester match exactly.',
                'alert alert-warning mt-3 mb-0'
            );
        } else {
            echo html_writer::div(
                html_writer::tag('strong', '❌ Course not found for linked') . '<br>' .
                'No project submission course matches this activity automatically. ' .
                'Please verify that a course exists whose shortname contains the Course Code (' . s($coursecode) . '), Study Mode (' . s($modecode) . '), and Semester (' . s($semestercode) . ') in a matching position.',
                'alert alert-danger mt-3 mb-0'
            );
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

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

    $course_fully_linked = false;
    if ($matching_course) {
        $m_parsed = tp_parse_course_shortname($matching_course->shortname);
        $m_modecode     = !empty($m_parsed['modecode']) ? $m_parsed['modecode'] : '';
        $m_semestercode = !empty($m_parsed['semestercode']) ? $m_parsed['semestercode'] : '';

        $mode_mismatch = (strval($modecode) !== 'N/A' && $m_modecode !== '' && strcasecmp($modecode, $m_modecode) !== 0);
        $sem_mismatch  = (strval($semestercode) !== 'N/A' && $m_semestercode !== '' && strcasecmp($semestercode, $m_semestercode) !== 0);

        if (!$mode_mismatch && !$sem_mismatch) {
            $course_fully_linked = true;
        }
    }

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

        } elseif (!$course_fully_linked) {
            $action = html_writer::span('Course not linked', 'text-danger small');

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
            $student->email ?: '—',
            $project_badge,
            $ct_badge,
            $ht_badge,
            $action,
        ];
    }

    echo html_writer::table($table);
}
