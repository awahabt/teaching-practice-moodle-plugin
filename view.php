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
        // ── Automatic matching: find the WORKSHOP course this student is enrolled in ──
        // Uses tp_find_student_workshop_course() which:
        //   • Requires WORKSHOP prefix in shortname TYPE segment.
        //   • Matches course code + mode + semester (always 2nd-from-last and last).
        //   • Checks both shortname segments AND course idnumber.
        //   • Filters to only courses this specific student is actively enrolled in.
        $matching_course = tp_find_student_workshop_course($course->shortname, $course->id, $USER->id);
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
    // For the course-level status card we still use tp_find_matching_course() so that we can
    // detect ANY match (and check if it has WORKSHOP prefix). Per-student resolution happens
    // inside the loop below using tp_find_student_workshop_course().
    $matching_course = tp_find_matching_course($course->shortname, $course->id);
    $parsed = tp_parse_course_shortname($course->shortname);
    $coursecode   = !empty($parsed['coursecode']) ? $parsed['coursecode'] : 'N/A';
    $modecode     = !empty($parsed['modecode']) ? $parsed['modecode'] : 'N/A';
    $semestercode = !empty($parsed['semestercode']) ? $parsed['semestercode'] : 'N/A';

    $course_fully_linked = false;
    $mode_mismatch = false;
    $sem_mismatch  = false;
    $mismatch_details = [];
    $matched_has_workshop = false;

    if ($matching_course) {
        $m_parsed = tp_parse_course_shortname($matching_course->shortname);
        $m_coursecode   = !empty($m_parsed['coursecode']) ? $m_parsed['coursecode'] : '';
        $m_modecode     = !empty($m_parsed['modecode']) ? $m_parsed['modecode'] : '';
        $m_semestercode = !empty($m_parsed['semestercode']) ? $m_parsed['semestercode'] : '';
        $matched_has_workshop = (stripos($m_parsed['type'], 'workshop') !== false);

        // Determine if there is a mismatch on Mode or Semester.
        $mode_mismatch = (strval($modecode) !== 'N/A' && $m_modecode !== '' && strcasecmp($modecode, $m_modecode) !== 0);
        $sem_mismatch  = (strval($semestercode) !== 'N/A' && $m_semestercode !== '' && strcasecmp($semestercode, $m_semestercode) !== 0);

        // Fully linked only when: mode + semester match AND the course has WORKSHOP prefix.
        if (!$mode_mismatch && !$sem_mismatch && $matched_has_workshop) {
            $course_fully_linked = true;
        } else {
            if ($mode_mismatch) {
                $mismatch_details[] = 'Study Mode (Expected: <strong>' . s($modecode) . '</strong>, Found: <strong>' . s($m_modecode) . '</strong>)';
            }
            if ($sem_mismatch) {
                $mismatch_details[] = 'Semester Code (Expected: <strong>' . s($semestercode) . '</strong>, Found: <strong>' . s($m_semestercode) . '</strong>)';
            }
            if (!$matched_has_workshop) {
                $mismatch_details[] = 'Course prefix is <strong>' . s($m_parsed['type'] ?: '(none)') . '</strong> — must be <strong>WORKSHOP</strong>';
            }
        }
    }

    // ── Render the course-level status / suggestion card ─────────────────────
    // Always shown unless fully linked (WORKSHOP + matching mode + semester).
    if (!$course_fully_linked) {
        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-header bg-light');
        echo html_writer::tag('h5', 'Linked WORKSHOP Course Integration', ['class' => 'mb-0']);
        echo html_writer::end_div();
        echo html_writer::start_div('card-body');

        if ($matching_course && ($mode_mismatch || $sem_mismatch)) {
            // Found a course with matching code but wrong mode/semester.
            echo html_writer::div(
                html_writer::tag('strong', '⚠️ Match Warning: A course was found with matching Course Code, but some fields do not match.') . '<br>' .
                'Linked Course: ' . html_writer::link(new moodle_url('/course/view.php', ['id' => $matching_course->id]),
                    s($matching_course->fullname) . ' (' . s($matching_course->shortname) . ')', ['target' => '_blank']) . '<br>' .
                'Mismatched fields:<br>' .
                html_writer::tag('ul', implode('', array_map(function($d) {
                    return html_writer::tag('li', $d);
                }, $mismatch_details))) .
                'Please align the shortnames so Course Code, Study Mode, and Semester match exactly, and ensure the course prefix is <strong>WORKSHOP</strong>.',
                'alert alert-warning mt-3 mb-0'
            );

        } elseif ($matching_course && !$matched_has_workshop) {
            // Format matches but WORKSHOP prefix missing.
            echo html_writer::div(
                html_writer::tag('strong', '⚠️ Format Match — WORKSHOP Prefix Required') . '<br>' .
                'A course matching the Course Code, Study Mode, and Semester was found, but its shortname does not start with <strong>WORKSHOP</strong>:<br>' .
                html_writer::link(new moodle_url('/course/view.php', ['id' => $matching_course->id]),
                    s($matching_course->fullname) . ' (' . s($matching_course->shortname) . ')', ['target' => '_blank']) . '<br>' .
                'This course will <strong>not</strong> be auto-linked. Rename its shortname to start with <code>WORKSHOP|</code> to enable auto-linking.',
                'alert alert-warning mt-3 mb-3'
            );

        } else {
            // No match at all.
            echo html_writer::div(
                html_writer::tag('strong', '❌ No WORKSHOP Course Found') . '<br>' .
                'No course with a <strong>WORKSHOP</strong> prefix was automatically matched for Course Code <strong>' . s($coursecode) .
                '</strong>, Mode <strong>' . s($modecode) . '</strong>, Semester <strong>' . s($semestercode) . '</strong>.',
                'alert alert-danger mt-3 mb-3'
            );
        }

        // ── Admin suggestion panel ────────────────────────────────────────────
        // Only shown to users with course management rights (admin / teacher).
        if (has_capability('moodle/course:manageactivities', $context) || is_siteadmin()) {
            $suggestions = tp_find_workshop_suggestions($course->shortname, $course->id);

            if (!empty($suggestions)) {
                $sugg_html = '';
                foreach ($suggestions as $s) {
                    $sc          = $s['course'];
                    $badge_class = $s['is_workshop'] ? 'badge-success' : 'badge-warning';
                    $badge_text  = $s['is_workshop'] ? 'WORKSHOP ✅' : 'Missing WORKSHOP prefix ⚠️';
                    $id_note     = $s['via_idnumber'] ? ' <em class="text-muted">(matched via idnumber)</em>' : '';

                    $sugg_html .= html_writer::tag('li',
                        html_writer::link(
                            new moodle_url('/course/view.php', ['id' => $sc->id]),
                            s($sc->fullname),
                            ['target' => '_blank', 'class' => 'font-weight-bold']
                        ) .
                        ' <code>(' . s($sc->shortname) . ')</code>' .
                        $id_note . ' ' .
                        html_writer::span($badge_text, 'badge ' . $badge_class),
                        ['class' => 'mb-2']
                    );
                }

                echo html_writer::div(
                    html_writer::tag('strong', '💡 Administrator Suggestion') . '<br>' .
                    'The following course(s) match the format criteria (Course Code, Mode, Semester) ' .
                    'and may be the correct project submission course. ' .
                    'If the correct course is listed below, rename its shortname to start with <code>WORKSHOP|</code> ' .
                    'and this activity will link automatically.' .
                    html_writer::tag('ul', $sugg_html, ['class' => 'mt-2 mb-0']),
                    'alert alert-info mt-2 mb-0'
                );
            }
        }

        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
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

    // ── TP course record (needed for per-student workshop lookup) ─────────────
    $tp_course = $DB->get_record('course', ['id' => $instance->course]);

    foreach ($students as $student) {

        tp_sync_student_performa($instance, $student->id);

        // ── Per-student: find the WORKSHOP course this student is enrolled in ──
        // Each student may be enrolled in a different WORKSHOP section/group for
        // the same programme code. tp_find_student_workshop_course() checks:
        //   • WORKSHOP prefix in shortname.
        //   • Matching coursecode + modecode + semestercode.
        //   • Matches on BOTH shortname segments AND course idnumber.
        //   • Restricted to courses the student is actively enrolled in.
        $s_workshop = $tp_course
            ? tp_find_student_workshop_course($tp_course->shortname, $tp_course->id, $student->id)
            : null;
        $s_assign   = $s_workshop ? tp_find_matching_assignment($s_workshop->id) : null;

        // Per-student linked check: code + mode + semester must all match.
        $student_linked = false;
        if ($s_workshop) {
            $sw_parsed = tp_parse_course_shortname($s_workshop->shortname);
            $sw_mode   = !empty($sw_parsed['modecode'])     ? $sw_parsed['modecode']     : '';
            $sw_sem    = !empty($sw_parsed['semestercode']) ? $sw_parsed['semestercode'] : '';
            $s_mm = (strval($modecode)     !== 'N/A' && $sw_mode !== '' && strcasecmp($modecode,     $sw_mode) !== 0);
            $s_sm = (strval($semestercode) !== 'N/A' && $sw_sem  !== '' && strcasecmp($semestercode, $sw_sem)  !== 0);
            $student_linked = !$s_mm && !$s_sm;
        }

        $project_status = 'not_submitted';
        if ($s_assign) {
            $submission = $DB->get_record('assign_submission', [
                'assignment' => $s_assign->id,
                'userid'     => $student->id,
                'latest'     => 1,
            ]);
            if ($submission && in_array($submission->status, ['submitted', 'graded'])) {
                $submitted_and_graded = tp_is_assignment_submitted_and_graded($student->id, $s_assign->id);
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

        } elseif (!$student_linked) {
            // Per-student: show the specific reason.
            if (!$s_workshop) {
                $action = html_writer::span('No WORKSHOP course found', 'text-danger small');
            } else {
                $action = html_writer::span('Course mismatch (mode/semester)', 'text-warning small');
            }

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
