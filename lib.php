<?php
/**
 * lib.php
 *
 * Required Moodle activity module functions + all plugin helper functions.
 * Moodle calls the teachingpractice_* functions automatically.
 */
defined('MOODLE_INTERNAL') || die();
// ── Status constants ─────────────────────────────────────────────────────────
define('TP_STATUS_PENDING',   'pending');
define('TP_STATUS_CT_DONE',   'ct_done');
define('TP_STATUS_COMPLETED', 'completed');

// ── Passing percentage ─────────────────────────────────────────────────────────
// Change this single value to adjust the minimum average mark required for the
// student to receive a certificate.  Example: 50 = 50 % average across all
// graded assignments in the linked project-submission course.
define('TP_PASSING_PERCENTAGE', 50);

// ============================================================================
// REQUIRED MOODLE MOD FUNCTIONS
// Moodle will not install the plugin without these functions.
// ============================================================================

/**
 * Called when a new activity instance is added to a course.
 * Inserts a row into the teachingpractice table.
 */
function teachingpractice_add_instance($data, $mform = null) {
    global $DB;
    $data->linked_course = 0;
    $data->linked_assign = 0;
    $data->student_role = 0;
    $data->ct_role      = 0;
    $data->ht_role      = 0;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('teachingpractice', $data);
}

/**
 * Called when an existing activity instance is edited and saved.
 * Updates the row in the teachingpractice table.
 */
function teachingpractice_update_instance($data, $mform = null) {
    global $DB;
    $data->linked_course = 0;
    $data->linked_assign = 0;
    $data->student_role = 0;
    $data->ct_role      = 0;
    $data->ht_role      = 0;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('teachingpractice', $data);
}

/**
 * Called when an activity instance is deleted from a course.
 * Removes all related data.
 */
function teachingpractice_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('teachingpractice', ['id' => $id])) {
        return false;
    }

    // Delete certificates linked to this instance
    $performa_ids = $DB->get_fieldset_select(
        'teachingpractice_performa', 'id', 'instanceid = ?', [$id]
    );
    if ($performa_ids) {
        list($sql, $params) = $DB->get_in_or_equal($performa_ids);
        $DB->delete_records_select('teachingpractice_certificate', "performaid $sql", $params);
    }

    // Delete all performa records for this instance
    $DB->delete_records('teachingpractice_performa', ['instanceid' => $id]);

    // Delete the instance itself
    $DB->delete_records('teachingpractice', ['id' => $id]);

    return true;
}

/**
 * Returns feature support flags for this module.
 * Tells Moodle which standard features this activity supports.
 */
function teachingpractice_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:          return true;
        case FEATURE_SHOW_DESCRIPTION:   return true;
        case FEATURE_BACKUP_MOODLE2:     return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        default: return null;
    }
}

// ============================================================================
// HELPER: Rating radio button options
// ============================================================================
function tp_get_rating_options() {
    return [
        'excellent'        => get_string('rating_excellent',        'mod_teachingpractice'),
        'verygood'         => get_string('rating_verygood',         'mod_teachingpractice'),
        'good'             => get_string('rating_good',             'mod_teachingpractice'),
        'fair'             => get_string('rating_fair',             'mod_teachingpractice'),
        'needsimprovement' => get_string('rating_needsimprovement', 'mod_teachingpractice'),
    ];
}

// ============================================================================
// HELPER: Recommendation radio button options
// ============================================================================
function tp_get_recommendation_options() {
    return [
        'completed'           => get_string('rec_completed',           'mod_teachingpractice'),
        'completed_minor'     => get_string('rec_completed_minor',     'mod_teachingpractice'),
        'further_improvement' => get_string('rec_further_improvement', 'mod_teachingpractice'),
    ];
}

// ============================================================================
// HELPER: Convert stored rating key to readable label
// ============================================================================
function tp_rating_label($value) {
    $map = [
        'excellent'        => 'Excellent',
        'verygood'         => 'Very Good',
        'good'             => 'Good',
        'fair'             => 'Fair',
        'needsimprovement' => 'Needs Improvement',
    ];
    return $map[$value] ?? '--';
}

// ============================================================================
// HELPER: Convert stored recommendation key to readable label
// ============================================================================
function tp_recommendation_label($value) {
    $map = [
        'completed'           => 'Successfully Completed Teaching Practice',
        'completed_minor'     => 'Successfully Completed with Minor Recommendations',
        'further_improvement' => 'Further Improvement Recommended',
    ];
    return $map[$value] ?? '--';
}

// ============================================================================
// HELPER: Fetch performa record for a student + instance
// ============================================================================
function tp_get_performa($instanceid, $studentid) {
    global $DB;
    return $DB->get_record('teachingpractice_performa', [
        'instanceid' => $instanceid,
        'studentid'  => $studentid,
    ]);
}

// ============================================================================
// HELPER: Section A field names (student teaching practice details)
// ============================================================================
function tp_section_a_field_names() {
    return [
        'registration_no',
        'school_name',
        'start_date',
        'end_date',
        'morning_time',
        'afternoon_time',
        'days_count',
        'cooperating_teacher_name',
        'subject_1',
        'subject_2',
        'subject_3',
    ];
}

// ============================================================================
// HELPER: Custom profile field shortname aliases for Section A mapping
// ============================================================================
function tp_profile_field_aliases() {
    return [
        'registration_no'          => ['registrationno', 'registration_no', 'regno', 'reg_no'],
        'school_name'              => ['schoolname', 'school_name', 'school', 'tp_school'],
        'start_date'               => ['tpstartdate', 'startdate', 'teachingpracticestart', 'tp_start_date'],
        'end_date'                 => ['tpenddate', 'enddate', 'teachingpracticeend', 'tp_end_date'],
        'morning_time'             => ['morningtime', 'morning_time', 'tp_morning_time'],
        'afternoon_time'           => ['afternoontime', 'afternoon_time', 'tp_afternoon_time'],
        'days_count'               => ['dayscount', 'days_count', 'tpdays', 'totaldays'],
        'cooperating_teacher_name' => ['cooperatingteacher', 'cooperating_teacher', 'cooperatingteachername', 'ctname'],
        'subject_1'                => ['subject1', 'subject_1', 'tp_subject_1'],
        'subject_2'                => ['subject2', 'subject_2', 'tp_subject_2'],
        'subject_3'                => ['subject3', 'subject_3', 'tp_subject_3'],
    ];
}

// ============================================================================
// HELPER: Parse a date value from profile text or timestamp
// ============================================================================
function tp_parse_date_value($value) {
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    $timestamp = strtotime((string) $value);
    return $timestamp ? $timestamp : 0;
}

// ============================================================================
// HELPER: Merge non-empty Section A values from source into target
// ============================================================================
function tp_merge_section_a_data($target, $source) {
    foreach (tp_section_a_field_names() as $field) {
        if (!isset($source->$field) || $source->$field === '' || $source->$field === null) {
            continue;
        }
        if (in_array($field, ['start_date', 'end_date', 'days_count'], true)) {
            if ($field === 'days_count') {
                $target->$field = (int) $source->$field;
            } else {
                $parsed = tp_parse_date_value($source->$field);
                if ($parsed) {
                    $target->$field = $parsed;
                }
            }
        } else if (empty($target->$field)) {
            $target->$field = $source->$field;
        }
    }
    return $target;
}

// ============================================================================
// HELPER: Read a custom profile field using known shortname aliases
// ============================================================================
function tp_get_profile_value(stdClass $profile, $field) {
    $aliases = tp_profile_field_aliases();
    if (empty($aliases[$field])) {
        return '';
    }
    foreach ($aliases[$field] as $shortname) {
        if (isset($profile->{$shortname}) && $profile->{$shortname} !== '') {
            return trim((string) $profile->{$shortname});
        }
    }
    return '';
}

// ============================================================================
// HELPER: Map an associative array (JSON submission) onto Section A fields
// ============================================================================
function tp_map_section_a_array(array $input) {
    $data = new stdClass();
    $aliases = tp_profile_field_aliases();
    foreach (tp_section_a_field_names() as $field) {
        $keys = array_merge([$field], $aliases[$field] ?? []);
        foreach ($keys as $key) {
            if (!isset($input[$key]) || $input[$key] === '') {
                continue;
            }
            if (in_array($field, ['start_date', 'end_date'], true)) {
                $data->$field = tp_parse_date_value($input[$key]);
            } else if ($field === 'days_count') {
                $data->$field = (int) $input[$key];
            } else {
                $data->$field = trim((string) $input[$key]);
            }
            break;
        }
    }
    return $data;
}

// ============================================================================
// HELPER: Parse assignment online-text submission for Section A values
// Supports JSON payloads or labelled lines such as "School Name: ..."
// ============================================================================
function tp_parse_section_a_text($text) {
    $data = new stdClass();
    if ($text === null || $text === '') {
        return $data;
    }

    $plain = trim(html_to_text($text, 0));
    if ($plain === '') {
        return $data;
    }

    $decoded = json_decode($plain, true);
    if (is_array($decoded)) {
        return tp_map_section_a_array($decoded);
    }

    $patterns = [
        'registration_no'          => '/registration\s*(?:no\.?|number|#)\s*[:\-]\s*(.+)$/iu',
        'school_name'              => '/school\s*name\s*[:\-]\s*(.+)$/iu',
        'start_date'               => '/(?:teaching practice )?start\s*date\s*[:\-]\s*(.+)$/iu',
        'end_date'                 => '/(?:teaching practice )?end\s*date\s*[:\-]\s*(.+)$/iu',
        'morning_time'             => '/morning\s*time(?:\s*\(arrival\))?\s*[:\-]\s*(.+)$/iu',
        'afternoon_time'           => '/afternoon\s*time(?:\s*\(departure\))?\s*[:\-]\s*(.+)$/iu',
        'days_count'               => '/(?:total )?days(?:\s*of teaching practice)?\s*[:\-]\s*(\d+)/iu',
        'cooperating_teacher_name' => '/(?:name of )?cooperating\s*teacher(?:\s*name)?\s*[:\-]\s*(.+)$/iu',
        'subject_1'                => '/subject\s*1\s*[:\-]\s*(.+)$/iu',
        'subject_2'                => '/subject\s*2(?:\s*\(optional\))?\s*[:\-]\s*(.+)$/iu',
        'subject_3'                => '/subject\s*3(?:\s*\(optional\))?\s*[:\-]\s*(.+)$/iu',
    ];

    foreach ($patterns as $field => $pattern) {
        if (preg_match($pattern, $plain, $matches)) {
            $value = trim($matches[1]);
            if ($field === 'days_count') {
                $data->$field = (int) $value;
            } else if (in_array($field, ['start_date', 'end_date'], true)) {
                $data->$field = tp_parse_date_value($value);
            } else {
                $data->$field = $value;
            }
        }
    }

    return $data;
}

// ============================================================================
// HELPER: Fetch Section A data from Moodle user profile fields
// ============================================================================
function tp_fetch_section_a_from_profile(stdClass $student) {
    global $CFG;
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $data   = new stdClass();
    $profile = profile_user_record($student->id, false);

    if (!empty($student->idnumber)) {
        $data->registration_no = trim($student->idnumber);
    }
    if (!empty($student->institution)) {
        $data->school_name = trim($student->institution);
    } else if (!empty($student->department)) {
        $data->school_name = trim($student->department);
    }

    foreach (tp_section_a_field_names() as $field) {
        $value = tp_get_profile_value($profile, $field);
        if ($value === '') {
            continue;
        }
        if (in_array($field, ['start_date', 'end_date'], true)) {
            $data->$field = tp_parse_date_value($value);
        } else if ($field === 'days_count') {
            $data->$field = (int) $value;
        } else if (empty($data->$field)) {
            $data->$field = $value;
        }
    }

    return $data;
}

// ============================================================================
// HELPER: Fetch Section A data from the linked assignment submission
// ============================================================================
function tp_fetch_section_a_from_assignment($studentid, $assignid) {
    global $DB, $CFG;

    if (!$assignid) {
        return new stdClass();
    }

    require_once($CFG->dirroot . '/mod/assign/locallib.php');

    $assignrecord = $DB->get_record('assign', ['id' => $assignid], '*', IGNORE_MISSING);
    if (!$assignrecord) {
        return new stdClass();
    }

    $cm = get_coursemodule_from_instance('assign', $assignid, $assignrecord->course, false, IGNORE_MISSING);
    if (!$cm) {
        return new stdClass();
    }

    $context = context_module::instance($cm->id);
    $assign  = new assign($context, $cm, $assignrecord->course);
    $submission = $assign->get_user_submission($studentid, false);
    if (!$submission) {
        return new stdClass();
    }

    $data = new stdClass();

    $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submission->id]);
    if ($onlinetext && !empty($onlinetext->onlinetext)) {
        tp_merge_section_a_data($data, tp_parse_section_a_text($onlinetext->onlinetext));
    }

    return $data;
}

// ============================================================================
// HELPER: Fetch Section A data from all supported Moodle sources
// Priority: assignment submission, then user profile / idnumber
// ============================================================================
function tp_fetch_moodle_section_a_data(stdClass $student, stdClass $instance) {
    global $DB;
    $data = new stdClass();
    tp_merge_section_a_data($data, tp_fetch_section_a_from_profile($student));

    // Automatically resolve the matching course and assignment from the shortname.
    $course = $DB->get_record('course', ['id' => $instance->course]);
    if ($course) {
        $matching_course = tp_find_matching_course($course->shortname, $course->id);
        if ($matching_course) {
            $matching_assign = tp_find_matching_assignment($matching_course->id);
            if ($matching_assign) {
                tp_merge_section_a_data($data, tp_fetch_section_a_from_assignment($student->id, $matching_assign->id));
            }
        }
    }
    return $data;
}

// ============================================================================
// HELPER: Create or refresh performa Section A from Moodle after assignment submit
// ============================================================================
function tp_sync_student_performa(stdClass $instance, $studentid) {
    global $DB;

    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    $fetched = tp_fetch_moodle_section_a_data($student, $instance);
    $performa = tp_get_performa($instance->id, $studentid);

    if (!$performa) {
        $performa = new stdClass();
        $performa->instanceid   = $instance->id;
        $performa->studentid    = $studentid;
        $performa->status       = TP_STATUS_PENDING;
        $performa->timecreated  = time();
        foreach (tp_section_a_field_names() as $field) {
            $performa->$field = tp_normalise_section_a_value($field, $fetched->$field ?? '');
        }
        $performa->timemodified = time();
        $performa->id = $DB->insert_record('teachingpractice_performa', $performa);
        return $performa;
    }

    if ($performa->status === TP_STATUS_PENDING) {
        foreach (tp_section_a_field_names() as $field) {
            if (!empty($fetched->$field)) {
                $performa->$field = tp_normalise_section_a_value($field, $fetched->$field);
            }
        }
        $performa->timemodified = time();
        $DB->update_record('teachingpractice_performa', $performa);
    }

    return $performa;
}

// ============================================================================
// HELPER: Build Section A data object for display
// ============================================================================
function tp_build_section_a_data($student, $performa = null, $instance = null) {
    $data = new stdClass();
    foreach (tp_section_a_field_names() as $field) {
        $data->$field = ($performa && isset($performa->$field)) ? $performa->$field : '';
    }

    if ($instance) {
        tp_merge_section_a_data($data, tp_fetch_moodle_section_a_data($student, $instance));
    } else if (empty($data->registration_no) && !empty($student->idnumber)) {
        $data->registration_no = $student->idnumber;
    }

    if ($performa) {
        foreach (tp_section_a_field_names() as $field) {
            if (!empty($performa->$field)) {
                $data->$field = $performa->$field;
            }
        }
    }

    return $data;
}

// ============================================================================
// HELPER: Whether Section A has been synced for this student
// ============================================================================
function tp_has_section_a_data($performa) {
    return !empty($performa);
}

// ============================================================================
// HELPER: Format a timestamp for certificate display
// ============================================================================
function tp_format_certificate_date($timestamp) {
    if (empty($timestamp)) {
        return '--';
    }
    return userdate($timestamp, '%d %B %Y');
}

// ============================================================================
// HELPER: Render Section A as certificate-style HTML
// ============================================================================
function tp_render_section_a_certificate($student, $data, array $options = []) {
    global $DB, $COURSE;

    $certno = $options['certno'] ?? get_string('certno_pending', 'mod_teachingpractice');
    $subjects = array_filter([
        $data->subject_1 ?? '',
        $data->subject_2 ?? '',
        $data->subject_3 ?? '',
    ]);

    $val = function($value) {
        return !empty($value) ? s($value) : '--';
    };

    $startdate = tp_format_certificate_date($data->start_date ?? 0);
    $enddate   = tp_format_certificate_date($data->end_date ?? 0);
    $days      = !empty($data->days_count) ? (int) $data->days_count : '--';

    $course_shortname = '';
    $matching_course = null;
    $c = null;
    if (!empty($data->instanceid)) {
        $tp = $DB->get_record('teachingpractice', ['id' => $data->instanceid]);
        if ($tp) {
            $c = $DB->get_record('course', ['id' => $tp->course]);
            if ($c) {
                $matching_course = tp_find_matching_course($c->shortname, $c->id);
                if ($matching_course) {
                    $course_shortname = $matching_course->shortname;
                } else {
                    $course_shortname = $c->shortname;
                }
            }
        }
    }
    if (empty($course_shortname)) {
        $matching_course = tp_find_matching_course($COURSE->shortname, $COURSE->id);
        if ($matching_course) {
            $course_shortname = $matching_course->shortname;
        } else {
            $course_shortname = $COURSE->shortname;
        }
        $c = $COURSE;
    }

    $extracted = tp_extract_course_code_and_school_name($c ?: $COURSE, $matching_course);
    $display_coursecode = !empty($extracted['coursecode']) ? $extracted['coursecode'] : $course_shortname;
    $display_schoolname = !empty($extracted['schoolname']) ? $extracted['schoolname'] : ($data->school_name ?? '');

    $html = html_writer::start_div('tp-section-a-cert');
    $html .= html_writer::tag('div', get_string('certno_label', 'mod_teachingpractice') . ' ' .
        html_writer::tag('strong', s($certno)), ['class' => 'tp-cert-no']);
    $html .= html_writer::tag('div', get_string('certificate_title', 'mod_teachingpractice'),
        ['class' => 'tp-cert-title']);

    $html .= html_writer::start_div('tp-cert-body');
    $html .= html_writer::tag('p',
        'This is to certify that Mr./Ms./Mrs. ' .
        html_writer::tag('strong', fullname($student)) . ', Registration No. ' .
        html_writer::tag('strong', s($student->email)) .
        ', has successfully completed the Teaching Practice (Course Code: ' . s($display_coursecode) . ') at ' .
        html_writer::tag('strong', $val($display_schoolname)) .
        ' from ' . html_writer::tag('strong', $startdate) .
        ' to ' . html_writer::tag('strong', $enddate) .
        // ', with timings ' .
        // html_writer::tag('strong', $val($data->morning_time ?? '') . ' â€“ ' . $val($data->afternoon_time ?? '')) .
        ', completing a total of ' . html_writer::tag('strong', $days . ' days') .
        ' of teaching practice under the supervision of ' .
        html_writer::tag('strong', $val($data->cooperating_teacher_name ?? '')) . '.'
    );

    if (!empty($subjects)) {
        $html .= html_writer::tag('p', get_string('certificate_subjects_intro', 'mod_teachingpractice'));
        $items = '';
        foreach ($subjects as $subject) {
            $items .= html_writer::tag('li', s($subject));
        }
        $html .= html_writer::tag('ul', $items, ['class' => 'tp-cert-subjects']);
    }
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::tag('style', '
        .tp-section-a-cert {
            border: 3px solid #0d7215ff;
            padding: 24px 28px;
            margin: 0 0 20px;
            font-family: "Times New Roman", Times, serif;
            background: #fff;
            color: #1a1a1a;
        }
        .tp-section-a-cert .tp-cert-no { text-align: right; font-size: 0.9rem; color: #666; margin-bottom: 8px; }
        .tp-section-a-cert .tp-cert-title {
            text-align: center; font-size: 2rem; font-weight: bold; color: #0d7215ff;
            text-transform: uppercase; letter-spacing: 1px; margin: 12px 0 18px;
            text-decoration: underline;
        }
        .tp-section-a-cert .tp-cert-body { font-size: 1.2rem; line-height: 1.85; text-align: justify; }
        .tp-section-a-cert .tp-cert-body strong { color: #0d7215ff; }
        .tp-section-a-cert .tp-cert-subjects { margin: 10px 0 0 24px; }
    ');

    return $html;
}

// ============================================================================
// HELPER: Normalise a Section A value for database storage
// ============================================================================
function tp_normalise_section_a_value($field, $value) {
    if (in_array($field, ['start_date', 'end_date', 'days_count'], true)) {
        return ($value === '' || $value === null) ? 0 : (int) $value;
    }
    return ($value === null) ? '' : (string) $value;
}

// ============================================================================
// HELPER: Copy Section A values from a form submission object onto a record
// ============================================================================
function tp_apply_section_a_data($record, $data) {
    foreach (tp_section_a_field_names() as $field) {
        $record->$field = tp_normalise_section_a_value($field, $data->$field ?? '');
    }
    return $record;
}

// ============================================================================
// HELPER: Check if student has submitted the required assignment in Course A
// ============================================================================
function tp_has_submitted_project($studentid, $assignmentid) {
    global $DB;

    if (!$assignmentid) {
        return false;
    }

    $submission = $DB->get_record('assign_submission', [
        'assignment' => $assignmentid,
        'userid'     => $studentid,
        'latest'     => 1,
    ]);

    if (!$submission) {
        return false;
    }

    return in_array($submission->status, ['submitted', 'graded']);
}

// ============================================================================
// HELPER: Determine which TP role the current user has in this module context.
// Compares user's roles in the course against the instance role config.
// Returns: 'student' | 'ct' | 'ht' | 'unknown'
// ============================================================================
function tp_get_user_role($instance, $context) {
    global $USER;

    $user_roles    = get_user_roles($context, $USER->id, true);
    $user_role_ids = array_column($user_roles, 'roleid');

    $ht_role = get_config('mod_teachingpractice', 'ht_role');
    $ct_role = get_config('mod_teachingpractice', 'ct_role');
    $student_role = get_config('mod_teachingpractice', 'student_role');

    // Priority: HT > CT > Student (in case someone has multiple roles)
    if ($ht_role && in_array((int)$ht_role, $user_role_ids)) {
        return 'ht';
    }
    if ($ct_role && in_array((int)$ct_role, $user_role_ids)) {
        return 'ct';
    }
    if ($student_role && in_array((int)$student_role, $user_role_ids)) {
        return 'student';
    }

    return 'unknown';
}

// ============================================================================
// HELPER: Get students who should appear on the CT/HT evaluation dashboard.
//
// Uses the teaching practice course context (where role assignments live),
// and also includes anyone who has submitted the linked assignment.
// ============================================================================
function tp_get_instance_students($instance, $context) {
    global $DB;

    $studentids = [];
    $coursecontext = $context->get_course_context(true);
    $studentroleid = (int) get_config('mod_teachingpractice', 'student_role');

    if ($studentroleid) {
        $enrolled = get_role_users(
            $studentroleid,
            $coursecontext,
            false,
            '',
            'u.lastname ASC, u.firstname ASC'
        );
        foreach ($enrolled as $user) {
            $studentids[$user->id] = $user->id;
        }
    }

    // Automatically resolve the matching assignment from the shortname.
    $course = $DB->get_record('course', ['id' => $instance->course]);
    if ($course) {
        $matching_course = tp_find_matching_course($course->shortname, $course->id);
        if ($matching_course) {
            $matching_assign = tp_find_matching_assignment($matching_course->id);
            if ($matching_assign) {
                $submittedids = $DB->get_fieldset_sql(
                    "SELECT DISTINCT s.userid
                       FROM {assign_submission} s
                      WHERE s.assignment = :assignid
                        AND s.latest = 1
                        AND s.status IN ('submitted', 'graded')",
                    ['assignid' => $matching_assign->id]
                );
                foreach ($submittedids as $userid) {
                    $studentids[$userid] = $userid;
                }
            }
        }
    }

    if (empty($studentids)) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal(array_values($studentids));
    $students = $DB->get_records_select('user', "id $insql AND deleted = 0", $params);
    core_collator::asort_objects_by_property($students, 'lastname', core_collator::SORT_STRING);

    return $students;
}

// ============================================================================
// HELPER: Generate unique certificate number
// Format: AIOU-TP-YYYY-NNNNN
// ============================================================================
function tp_generate_certificate_number() {
    global $DB;
    $year  = date('Y');
    $count = $DB->count_records('teachingpractice_certificate');
    $seq   = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    return "AIOU-TP-{$year}-{$seq}";
}

// ============================================================================
// CORE: Issue certificate after both CT and HT submit their evaluations.
//
// Steps:
//   1. Generate certificate number
//   2. Insert record into teachingpractice_certificate
//   3. Update performa status to 'completed'
//   4. Enrol student in Course B (if not already enrolled)
//   5. Mark Course B as complete for student
//
// Returns: certificate number string on success | false if already issued
// ============================================================================
function tp_issue_certificate($performa, $course_b_id) {
    global $DB;

    // Skip if certificate already exists
    if ($DB->record_exists('teachingpractice_certificate', ['performaid' => $performa->id])) {
        return false;
    }

    $cert_no = tp_generate_certificate_number();

    // Insert certificate record
    $cert               = new stdClass();
    $cert->performaid   = $performa->id;
    $cert->instanceid   = $performa->instanceid;
    $cert->studentid    = $performa->studentid;
    $cert->certificate_no = $cert_no;
    $cert->issued_date  = time();
    $cert->timecreated  = time();
    $DB->insert_record('teachingpractice_certificate', $cert);

    // Mark performa as completed
    $DB->set_field('teachingpractice_performa', 'status',       TP_STATUS_COMPLETED, ['id' => $performa->id]);
    $DB->set_field('teachingpractice_performa', 'timemodified',  time(),              ['id' => $performa->id]);

    // Enrol student in Course B if not already enrolled
    $enrol_plugin    = enrol_get_plugin('manual');
    $enrol_instances = enrol_get_instances($course_b_id, true);
    $student_role    = $DB->get_record('role', ['shortname' => 'student']);

    foreach ($enrol_instances as $enrol_instance) {
        if ($enrol_instance->enrol === 'manual') {
            $enrol_plugin->enrol_user($enrol_instance, $performa->studentid, $student_role->id);
            break;
        }
    }

    // Mark course completion for student in Course B
    $completion = new completion_completion([
        'userid' => $performa->studentid,
        'course' => $course_b_id,
    ]);
    $completion->mark_complete();

    return $cert_no;
}

/**
 * Helper: Extract course code and school name from course full name if it follows the pipe-delimited format:
 * "9028|Islamic Public School|16BH|ODL|2513"
 *
 * @param stdClass|null $course The current course object.
 * @param stdClass|null $matching_course The matching submission course object.
 * @return array Array containing 'coursecode' and 'schoolname' (both may be empty strings if not found).
 */
function tp_extract_course_code_and_school_name($course, $matching_course = null) {
    $coursecode = '';
    $schoolname = '';

    // First try the matching course's fullname if available.
    if ($matching_course && !empty($matching_course->fullname) && strpos($matching_course->fullname, '|') !== false) {
        $fullname = $matching_course->fullname;
    } else if ($course && !empty($course->fullname) && strpos($course->fullname, '|') !== false) {
        $fullname = $course->fullname;
    } else {
        $fullname = '';
    }

    if ($fullname !== '') {
        $parts = explode('|', $fullname);
        if (count($parts) >= 2) {
            $coursecode = trim($parts[0]);
            $schoolname = trim($parts[1]);
        }
    }

    return [
        'coursecode' => $coursecode,
        'schoolname' => $schoolname,
    ];
}

// ============================================================================
// HELPER: Parse course shortname segments.
//
// Pipe-delimited format:  TYPE|COURSECODE|GROUP|BATCH|MODE|SEMESTER
// Example:                WORKSHOP|9028|G1474|16BH|ODL|2513
//
// Returns an array with:
//   'pipe_format'  true if pipe-delimited
//   'type'         first segment (e.g. WORKSHOP)
//   'tail'         everything after the first segment (e.g. 9028|G1474|16BH|ODL|2513)
//   'coursecode'   second segment (e.g. 9028)
//   'semestercode' sixth segment (e.g. 2513)
//   'parts'        all segments as array
// ============================================================================
function tp_parse_course_shortname($shortname) {
    $shortname = trim($shortname);

    // ── Pipe-delimited format ─────────────────────────────────────────────────
    // e.g. WORKSHOP|9028|G1474|16BH|ODL|2513 (6 parts with type prefix)
    // or e.g. 9028|G1474|16BH|ODL|2513 (5 parts without type prefix)
    if (strpos($shortname, '|') !== false) {
        $parts = array_map('trim', explode('|', $shortname));

        if (count($parts) === 5 || (isset($parts[0]) && is_numeric($parts[0]))) {
            // 5-part shortname: 9028 | G1474 | 16BH | ODL | 2513
            $type         = '';
            $coursecode   = isset($parts[0]) ? $parts[0] : '';
            $modecode     = isset($parts[3]) ? $parts[3] : '';
            $semestercode = isset($parts[4]) ? $parts[4] : '';
            $tail         = $shortname;
        } else {
            // 6-part shortname: WORKSHOP | 9028 | G1474 | 16BH | ODL | 2513
            $type         = isset($parts[0]) ? $parts[0] : '';
            $tail_parts   = array_slice($parts, 1);
            $tail         = implode('|', $tail_parts);
            $coursecode   = isset($parts[1]) ? $parts[1] : '';
            $modecode     = isset($parts[4]) ? $parts[4] : '';
            $semestercode = isset($parts[5]) ? $parts[5] : '';
        }

        return [
            'pipe_format'  => true,
            'type'         => $type,
            'tail'         => $tail,
            'coursecode'   => $coursecode,
            'modecode'     => $modecode,
            'semestercode' => $semestercode,
            'parts'        => $parts,
        ];
    }

    // Legacy heuristic (non-pipe shortnames)
    $coursecode   = '';
    $semestercode = '';

    $semester_regex = '/\b(autumn|spring|sem)[-_]?[0-9]{2,4}\b/i';
    if (preg_match($semester_regex, $shortname, $matches)) {
        $semestercode = $matches[0];
        $remaining    = str_replace($semestercode, '', $shortname);
    } else {
        $year_regex = '/[-_]?[0-9]{4}\b/';
        if (preg_match($year_regex, $shortname, $matches)) {
            $semestercode = trim($matches[0], '-_');
            $remaining    = str_replace($matches[0], '', $shortname);
        } else {
            $remaining = $shortname;
        }
    }

    $clean_regex = '/\b(tp|teachingpractice|teaching_practice)\b/i';
    $remaining   = preg_replace($clean_regex, '', $remaining);
    $remaining   = trim($remaining, ' -_');
    $coursecode  = $remaining;

    return [
        'pipe_format'  => false,
        'type'         => '',
        'tail'         => '',
        'coursecode'   => $coursecode,
        'modecode'     => '',
        'semestercode' => $semestercode,
        'parts'        => [],
    ];
}

// ============================================================================
// HELPER: Find the matching linked Course based on current course shortname.
//
// Matching rule (pipe-delimited shortnames TYPE|COURSECODE|GROUP|BATCH|MODE|SEMESTER):
//   Extracts coursecode   = segment[1] or segment[0]
//   Extracts modecode     = segment[4] or segment[3]
//   Extracts semestercode = segment[5] or segment[4]
//
//   Finds any other course that has the SAME coursecode, SAME mode, and SAME semester.
//   The assignment course does NOT need any specific type prefix (it can be ASSIGN,
//   COURSE, ED, or have no prefix at all).
//
// Results are cached per request to avoid repeated DB queries inside loops.
// ============================================================================
function tp_find_matching_course($current_course_shortname, $exclude_course_id = 0, $userid = 0) {
    global $DB;

    // Static request-level cache
    static $cache = [];
    $cache_key = $current_course_shortname . '|excl:' . $exclude_course_id . '|u:' . $userid;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $parsed       = tp_parse_course_shortname($current_course_shortname);
    $is_pipe      = $parsed['pipe_format'];
    $coursecode   = $parsed['coursecode'];
    $modecode     = $parsed['modecode'];
    $semestercode = $parsed['semestercode'];

    $exclude_sql    = $exclude_course_id ? ' AND c.id != :excludeid' : '';
    $exclude_params = $exclude_course_id ? ['excludeid' => $exclude_course_id] : [];

    // Enrollment join (optional): narrow to courses the student is enrolled in.
    if ($userid > 0) {
        $enrol_join = "JOIN {enrol} e        ON e.courseid = c.id AND e.status = 0
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                                AND ue.userid = :enrol_userid
                                                AND ue.status = 0";
        $enrol_params = ['enrol_userid' => $userid];
    } else {
        $enrol_join   = '';
        $enrol_params = [];
    }

    // =========================================================================
    // PIPE FORMAT: Match by COURSECODE + MODE + SEMESTER
    // =========================================================================
    if ($is_pipe && !empty($coursecode)) {

        // Strategy 1: Match Course Code + Study Mode + Semester Code
        if (!empty($semestercode) && !empty($modecode)) {
            $like_code = '%' . $DB->sql_like_escape($coursecode) . '%';
            $like_sem  = '%' . $DB->sql_like_escape($semestercode) . '%';

            $params = array_merge($exclude_params, $enrol_params, [
                'code_like' => $like_code,
                'sem_like'  => $like_sem,
            ]);

            $sql = "SELECT c.id, c.fullname, c.shortname
                      FROM {course} c
                      $enrol_join
                     WHERE c.id > 1
                       AND " . $DB->sql_like('c.shortname', ':code_like', false) . "
                       AND " . $DB->sql_like('c.shortname', ':sem_like',  false) .
                       $exclude_sql . "
                     ORDER BY c.id ASC";

            $candidates = $DB->get_records_sql($sql, $params, 0, 20);

            $non_tp = [];
            $tp_type = [];

            foreach ($candidates as $c) {
                $cp    = tp_parse_course_shortname($c->shortname);
                $ccode = $cp['coursecode'];
                $cmode = $cp['modecode'];
                $csem  = $cp['semestercode'];

                // Exact match required for code, mode, and semester.
                if (strcasecmp($ccode, $coursecode) !== 0 ||
                    (!empty($modecode) && !empty($cmode) && strcasecmp($cmode, $modecode) !== 0) ||
                    (!empty($semestercode) && !empty($csem) && strcasecmp($csem, $semestercode) !== 0)) {
                    continue;
                }

                $ctype = strtolower($cp['type']);
                $is_tp = ($ctype !== '' && (strpos($ctype, 'tp') !== false
                       || strpos($ctype, 'workshop') !== false
                       || strpos($ctype, 'teaching') !== false));

                if ($is_tp) {
                    $tp_type[] = $c;
                } else {
                    $non_tp[] = $c;
                }
            }

            $result = !empty($non_tp) ? reset($non_tp) : (!empty($tp_type) ? reset($tp_type) : null);
            if ($result) {
                $cache[$cache_key] = $result;
                return $result;
            }
        }

        // Strategy 2: Fallback to Course Code + Semester Code match
        if (!empty($semestercode)) {
            $like_code = '%' . $DB->sql_like_escape($coursecode) . '%';
            $like_sem  = '%' . $DB->sql_like_escape($semestercode) . '%';

            $params = array_merge($exclude_params, $enrol_params, [
                'code_like' => $like_code,
                'sem_like'  => $like_sem,
            ]);

            $sql = "SELECT c.id, c.fullname, c.shortname
                      FROM {course} c
                      $enrol_join
                     WHERE c.id > 1
                       AND " . $DB->sql_like('c.shortname', ':code_like', false) . "
                       AND " . $DB->sql_like('c.shortname', ':sem_like',  false) .
                       $exclude_sql . "
                     ORDER BY c.id ASC";

            $candidates = $DB->get_records_sql($sql, $params, 0, 20);

            $non_tp = [];
            $tp_type = [];

            foreach ($candidates as $c) {
                $cp    = tp_parse_course_shortname($c->shortname);
                $ccode = $cp['coursecode'];
                $csem  = $cp['semestercode'];

                if (strcasecmp($ccode, $coursecode) !== 0 ||
                    (!empty($semestercode) && !empty($csem) && strcasecmp($csem, $semestercode) !== 0)) {
                    continue;
                }

                $ctype = strtolower($cp['type']);
                $is_tp = ($ctype !== '' && (strpos($ctype, 'tp') !== false
                       || strpos($ctype, 'workshop') !== false
                       || strpos($ctype, 'teaching') !== false));

                if ($is_tp) {
                    $tp_type[] = $c;
                } else {
                    $non_tp[] = $c;
                }
            }

            $result = !empty($non_tp) ? reset($non_tp) : (!empty($tp_type) ? reset($tp_type) : null);
            if ($result) {
                $cache[$cache_key] = $result;
                return $result;
            }
        }

        // Fallback: coursecode-only match (no semester available or primary failed).
        $like_code2 = '%|' . $DB->sql_like_escape($coursecode) . '|%';
        $params2    = array_merge($exclude_params, $enrol_params, ['code_like2' => $like_code2]);
        $sql2 = "SELECT c.id, c.fullname, c.shortname
                   FROM {course} c
                   $enrol_join
                  WHERE c.id > 1
                    AND " . $DB->sql_like('c.shortname', ':code_like2', false) .
                    $exclude_sql . "
                  ORDER BY c.id ASC";
        $courses2 = $DB->get_records_sql($sql2, $params2, 0, 10);
        foreach ($courses2 as $c) {
            $cparts = explode('|', $c->shortname);
            $ccode  = isset($cparts[1]) ? trim($cparts[1]) : '';
            if (strcasecmp($ccode, $coursecode) !== 0) {
                continue;
            }
            $ctype = strtolower(trim($cparts[0] ?? ''));
            if (strpos($ctype, 'tp') === false
             && strpos($ctype, 'workshop') === false
             && strpos($ctype, 'teaching') === false) {
                $cache[$cache_key] = $c;
                return $c;
            }
        }
        if (!empty($courses2)) {
            $result = reset($courses2);
            $cache[$cache_key] = $result;
            return $result;
        }

        $cache[$cache_key] = null;
        return null;
    }

    // =========================================================================
    // LEGACY: non-pipe shortnames
    // =========================================================================
    if (empty($coursecode)) {
        $cache[$cache_key] = null;
        return null;
    }

    $exclude_sql = $exclude_course_id ? ' AND id != :excludeid' : '';

    $candidates = [];
    if (!empty($semestercode)) {
        $candidates[] = $coursecode . '-' . $semestercode;
        $candidates[] = $coursecode . '_' . $semestercode;
        $candidates[] = $coursecode . ' ' . $semestercode;
        $candidates[] = $coursecode . $semestercode;
    }
    $candidates[] = $coursecode;

    foreach ($candidates as $candidate) {
        $params = array_merge($exclude_params, ['shortname' => $candidate]);
        $sql = "SELECT id, fullname, shortname FROM {course}
                 WHERE id > 1 AND shortname = :shortname" . $exclude_sql;
        $course = $DB->get_record_sql($sql, $params);
        if ($course) {
            $cache[$cache_key] = $course;
            return $course;
        }
    }

    if (!empty($semestercode)) {
        $params = array_merge($exclude_params, ['search1' => '%' . $coursecode . '%' . $semestercode . '%']);
        $sql = "SELECT id, fullname, shortname
                  FROM {course}
                 WHERE id > 1
                   AND " . $DB->sql_like('shortname', ':search1', false) . $exclude_sql;
        $course = $DB->get_record_sql($sql, $params);
        if ($course) {
            $cache[$cache_key] = $course;
            return $course;
        }
    }

    $params = array_merge($exclude_params, ['search2' => '%' . $coursecode . '%']);
    $sql = "SELECT id, fullname, shortname
              FROM {course}
             WHERE id > 1
               AND " . $DB->sql_like('shortname', ':search2', false) . $exclude_sql;
    $courses = $DB->get_records_sql($sql, $params);
    if ($courses) {
        foreach ($courses as $c) {
            if (stripos($c->shortname, 'tp') === false && stripos($c->shortname, 'teaching') === false) {
                $cache[$cache_key] = $c;
                return $c;
            }
        }
        $result = reset($courses);
        $cache[$cache_key] = $result;
        return $result;
    }

    $cache[$cache_key] = null;
    return null;
}
// ============================================================================
function tp_find_matching_assignment($courseid) {
    global $DB;
    $assigns = $DB->get_records('assign', ['course' => $courseid], 'id ASC');
    if (empty($assigns)) {
        return null;
    }
    if (count($assigns) === 1) {
        return reset($assigns);
    }

    // Prioritize names containing common keywords.
    $keywords = ['teaching practice', 'teachingpractice', 'project', 'submission', 'assignment'];
    foreach ($keywords as $keyword) {
        foreach ($assigns as $a) {
            if (stripos($a->name, $keyword) !== false) {
                return $a;
            }
        }
    }

    return reset($assigns);
}

// ============================================================================
// HELPER: Check if student's assignment has been submitted AND graded
// ============================================================================
function tp_is_assignment_submitted_and_graded($studentid, $assignmentid) {
    global $DB;
    if (!$assignmentid) {
        return false;
    }

    $submission = $DB->get_record('assign_submission', [
        'assignment' => $assignmentid,
        'userid'     => $studentid,
        'latest'     => 1,
    ]);

    if (!$submission || !in_array($submission->status, ['submitted', 'graded'])) {
        return false;
    }

    // Check gradebook/assign_grades for a valid non-negative grade.
    $grade = $DB->get_record('assign_grades', [
        'assignment' => $assignmentid,
        'userid'     => $studentid,
    ]);

    if (!$grade || $grade->grade === null || $grade->grade < 0) {
        return false;
    }

    return true;
}
// ============================================================================
// HELPER: Compute student's average grade percentage across ALL assignments
//         in a given course.
//
// Returns: float (0-100) average percentage if at least one assignment is
//          graded, or null if no graded assignments exist yet.
// ============================================================================
function tp_get_student_average_grade_percentage($studentid, $courseid) {
    global $DB;

    // Fetch every assignment in this course.
    $assigns = $DB->get_records('assign', ['course' => $courseid], 'id ASC');
    if (empty($assigns)) {
        return null;
    }

    $total_pct    = 0.0;
    $graded_count = 0;

    foreach ($assigns as $assign) {
        // Skip assignments with no max grade defined.
        if (empty($assign->grade) || $assign->grade <= 0) {
            continue;
        }

        // Get the student's grade record for this assignment.
        $grade_rec = $DB->get_record('assign_grades', [
            'assignment' => $assign->id,
            'userid'     => $studentid,
        ]);

        // Skip if not yet graded (null or negative = ungraded / no submission).
        if (!$grade_rec || $grade_rec->grade === null || $grade_rec->grade < 0) {
            continue;
        }

        // Compute percentage of max mark for this assignment.
        $pct           = ($grade_rec->grade / $assign->grade) * 100.0;
        $total_pct    += $pct;
        $graded_count++;
    }

    if ($graded_count === 0) {
        return null; // No graded assignments yet - cannot make a decision.
    }

    return round($total_pct / $graded_count, 2);
}

// ============================================================================
// HELPER: Returns true if the student's average grade across all assignments
//         in a course meets or exceeds TP_PASSING_PERCENTAGE.
//
// Returns: true  -> passed
//          false -> failed or not yet graded
// ============================================================================
function tp_student_passes_course($studentid, $courseid) {
    $avg = tp_get_student_average_grade_percentage($studentid, $courseid);
    if ($avg === null) {
        return false; // Not graded yet - treat as not passed.
    }
    return $avg >= TP_PASSING_PERCENTAGE;
}

/**
 * Serves the files stored in the teaching practice component (e.g. signature images).
 */
function mod_teachingpractice_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB;

    // Settings files are stored in the system context.
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    if ($filearea !== 'signature_image') {
        return false;
    }

    require_login();

    $itemid = (int)array_shift($args);

    $fs = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = $fs->get_file($context->id, 'mod_teachingpractice', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}