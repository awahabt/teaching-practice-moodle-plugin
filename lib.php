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

// ── Course linking format ────────────────────────────────────────────────────
// Both the teaching-practice (certificate) course and the project-submission
// course must share the pipe-delimited shortname format:
//   AIOU|COURSECODE|...|ROLE|MODE|SEMESTER
// ROLE (3rd-from-last segment) identifies which side of the link a course is:
//   TP  = the project submission course (where students submit their work)
//   SMS = the teaching practice / certificate course (where this activity lives)
// MODE (2nd-from-last) and SEMESTER (last) must match exactly between the two
// courses; COURSECODE (2nd segment) must also match exactly.
define('TP_REQUIRED_PREFIX',   'AIOU');
define('TP_ROLE_SUBMISSION',   'TP');
define('TP_ROLE_CERTIFICATE',  'SMS');

// ============================================================================
// REQUIRED MOODLE MOD FUNCTIONS
// Moodle will not install the plugin without these functions.
// ============================================================================

/**
 * Called when a new activity instance is added to a course.
 * Inserts a row into the researchproject table.
 */
function researchproject_add_instance($data, $mform = null) {
    global $DB;
    $data->linked_course = 0;
    $data->linked_assign = 0;
    $data->student_role = 0;
    $data->ct_role      = 0;
    $data->ht_role      = 0;
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('researchproject', $data);
}

function teachingpractice_add_instance($data, $mform = null) {
    return researchproject_add_instance($data, $mform);
}

/**
 * Called when an existing activity instance is edited and saved.
 * Updates the row in the researchproject table.
 */
function researchproject_update_instance($data, $mform = null) {
    global $DB;
    $data->linked_course = 0;
    $data->linked_assign = 0;
    $data->student_role = 0;
    $data->ct_role      = 0;
    $data->ht_role      = 0;
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('researchproject', $data);
}

function teachingpractice_update_instance($data, $mform = null) {
    return researchproject_update_instance($data, $mform);
}

/**
 * Called when an activity instance is deleted from a course.
 * Removes all related data.
 */
function researchproject_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('researchproject', ['id' => $id])) {
        return false;
    }

    // Delete certificates linked to this instance
    $performa_ids = $DB->get_fieldset_select(
        'researchproject_performa', 'id', 'instanceid = ?', [$id]
    );
    if ($performa_ids) {
        list($sql, $params) = $DB->get_in_or_equal($performa_ids);
        $DB->delete_records_select('researchproject_certificate', "performaid $sql", $params);
    }

    // Delete all performa records for this instance
    $DB->delete_records('researchproject_performa', ['instanceid' => $id]);

    // Delete the instance itself
    $DB->delete_records('researchproject', ['id' => $id]);

    return true;
}

function teachingpractice_delete_instance($id) {
    return researchproject_delete_instance($id);
}

/**
 * Returns feature support flags for this module.
 * Tells Moodle which standard features this activity supports.
 */
function researchproject_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:          return true;
        case FEATURE_SHOW_DESCRIPTION:   return true;
        case FEATURE_BACKUP_MOODLE2:     return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        default: return null;
    }
}

function teachingpractice_supports($feature) {
    return researchproject_supports($feature);
}

// ============================================================================
// HELPER: Rating radio button options
// ============================================================================
function tp_get_rating_options() {
    return [
        'excellent'        => get_string('rating_excellent',        'mod_researchproject'),
        'verygood'         => get_string('rating_verygood',         'mod_researchproject'),
        'good'             => get_string('rating_good',             'mod_researchproject'),
        'fair'             => get_string('rating_fair',             'mod_researchproject'),
        'needsimprovement' => get_string('rating_needsimprovement', 'mod_researchproject'),
    ];
}

// ============================================================================
// HELPER: Recommendation radio button options
// ============================================================================
function tp_get_recommendation_options() {
    return [
        'completed'           => get_string('rec_completed',           'mod_researchproject'),
        'completed_minor'     => get_string('rec_completed_minor',     'mod_researchproject'),
        'further_improvement' => get_string('rec_further_improvement', 'mod_researchproject'),
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
    return $DB->get_record('researchproject_performa', [
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
        $performa->id = $DB->insert_record('researchproject_performa', $performa);
        return $performa;
    }

    if ($performa->status === TP_STATUS_PENDING) {
        foreach (tp_section_a_field_names() as $field) {
            if (!empty($fetched->$field)) {
                $performa->$field = tp_normalise_section_a_value($field, $fetched->$field);
            }
        }
        $performa->timemodified = time();
        $DB->update_record('researchproject_performa', $performa);
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

    $certno = $options['certno'] ?? get_string('certno_pending', 'mod_researchproject');
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
        $tp = $DB->get_record('researchproject', ['id' => $data->instanceid]);
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
    $html .= html_writer::tag('div', get_string('certno_label', 'mod_researchproject') . ' ' .
        html_writer::tag('strong', s($certno)), ['class' => 'tp-cert-no']);
    $html .= html_writer::tag('div', get_string('certificate_title', 'mod_researchproject'),
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
        $html .= html_writer::tag('p', get_string('certificate_subjects_intro', 'mod_researchproject'));
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

    $ht_role = get_config('mod_researchproject', 'ht_role');
    $ct_role = get_config('mod_researchproject', 'ct_role');
    $student_role = get_config('mod_researchproject', 'student_role');

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
    $studentroleid = (int) get_config('mod_researchproject', 'student_role');

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
// HELPER: Find the user(s) holding a given role (Cooperating Teacher or Head
// Teacher) in a course context, with whatever contact details already exist
// for them in Moodle — no new data entry, just what's already in the DB
// (fullname + phone1/phone2 from their user profile).
//
// @return array of stdClass{ fullname, phone }
// ============================================================================
function tp_get_role_contacts($context, $roleid) {
    $roleid = (int) $roleid;
    if (empty($roleid)) {
        return [];
    }

    $coursecontext = $context->get_course_context(true);
    $users = get_role_users($roleid, $coursecontext, false, 'u.*', 'u.lastname ASC, u.firstname ASC');

    $contacts = [];
    foreach ($users as $u) {
        $phone = !empty($u->phone1) ? $u->phone1 : (!empty($u->phone2) ? $u->phone2 : '');
        $contacts[] = (object) [
            'fullname' => fullname($u),
            'phone'    => $phone,
        ];
    }
    return $contacts;
}

// ============================================================================
// HELPER: Generate unique certificate number
// Format: AIOU-TP-YYYY-NNNNN
// ============================================================================
function tp_generate_certificate_number() {
    global $DB;
    $year  = date('Y');
    $count = $DB->count_records('researchproject_certificate');
    $seq   = str_pad($count + 1, 5, '0', STR_PAD_LEFT);
    return "AIOU-TP-{$year}-{$seq}";
}

// ============================================================================
// CORE: Issue certificate after both CT and HT submit their evaluations.
//
// Steps:
//   1. Generate certificate number
//   2. Insert record into researchproject_certificate
//   3. Update performa status to 'completed'
//   4. Enrol student in Course B (if not already enrolled)
//   5. Mark Course B as complete for student
//
// Returns: certificate number string on success | false if already issued
// ============================================================================
function tp_issue_certificate($performa, $course_b_id) {
    global $DB;

    // Skip if certificate already exists
    if ($DB->record_exists('researchproject_certificate', ['performaid' => $performa->id])) {
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
    $DB->insert_record('researchproject_certificate', $cert);

    // Mark performa as completed
    $DB->set_field('researchproject_performa', 'status',       TP_STATUS_COMPLETED, ['id' => $performa->id]);
    $DB->set_field('researchproject_performa', 'timemodified',  time(),              ['id' => $performa->id]);

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
 * Helper: Extract course code and school name from course full name.
 *
 * Supported pipe-delimited full name format:
 *   TYPE | COURSECODE | SCHOOL NAME | ... (any number of extra segments)
 *
 * Examples:
 *   "AIOU|8608|Islamic Public School|TAXILA|SMS|ODL|2611"
 *   "Workshop|9028|Islamic Public School|16BH|ODL|2513"
 *
 * Extraction rules:
 *   parts[0] = TYPE  (e.g. AIOU, Workshop, Assignment — ignored for display)
 *   parts[1] = COURSECODE  (always the 2nd segment)
 *   parts[2] = SCHOOL NAME (always the 3rd segment)
 *
 * Falls back gracefully:
 *   - If only 2 parts: parts[0]=coursecode, parts[1]=schoolname (legacy).
 *   - If no pipes at all: returns empty strings (callers fall back to profile data).
 *
 * @param stdClass|null $course The current TP course object.
 * @param stdClass|null $matching_course The matching project-submission course object.
 * @return array ['coursecode' => string, 'schoolname' => string]
 */
function tp_extract_course_code_and_school_name($course, $matching_course = null) {
    $coursecode = '';
    $schoolname = '';

    $candidates = [];
    if ($course && !empty($course->fullname)) {
        $candidates[] = $course->fullname;
    }
    if ($course && !empty($course->shortname)) {
        $candidates[] = $course->shortname;
    }
    if ($matching_course && !empty($matching_course->fullname)) {
        $candidates[] = $matching_course->fullname;
    }
    if ($matching_course && !empty($matching_course->shortname)) {
        $candidates[] = $matching_course->shortname;
    }

    foreach ($candidates as $str) {
        if (strpos($str, '|') !== false) {
            $parts = array_map('trim', explode('|', $str));
            $n     = count($parts);

            // Format: PREFIX | COURSECODE | SCHOOL NAME | ...
            // Example: "AIOU|6997|GOVT. HIGH SCHOOL BHABRA, LALAZAR WAH CA|16BH|ODL|2313"
            //   parts[0] = Prefix (e.g. AIOU, Workshop - ignored)
            //   parts[1] = Course Code (e.g. 6997)
            //   parts[2] = School Name (e.g. GOVT. HIGH SCHOOL BHABRA, LALAZAR WAH CA)
            if ($n >= 3) {
                if (is_numeric($parts[0])) {
                    if (empty($coursecode)) $coursecode = $parts[0];
                    if (empty($schoolname)) $schoolname = $parts[1];
                } else {
                    if (empty($coursecode)) $coursecode = $parts[1];
                    if (empty($schoolname)) $schoolname = $parts[2];
                }
            } else if ($n === 2) {
                if (is_numeric($parts[0])) {
                    if (empty($coursecode)) $coursecode = $parts[0];
                    if (empty($schoolname)) $schoolname = $parts[1];
                } else {
                    if (empty($coursecode)) $coursecode = $parts[1];
                }
            }

            if (!empty($coursecode) && !empty($schoolname)) {
                break;
            }
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
// Pipe-delimited format:  AIOU|COURSECODE|...|ROLE|MODE|SEMESTER
// Example (submission):   AIOU|8608|ABBOTTABAD|19|TP|ODL|2611
// Example (certificate):  AIOU|8608|40|60N3QLVO|TAXILA|SMS|ODL|2611
//
// Returns an array with:
//   'pipe_format'  true if pipe-delimited
//   'type'         first segment (e.g. AIOU)
//   'tail'         everything after the first segment
//   'coursecode'   second segment (e.g. 8608)
//   'rolecode'     3rd-from-last segment (e.g. TP or SMS)
//   'modecode'     2nd-from-last segment (e.g. ODL)
//   'semestercode' last segment (e.g. 2611)
//   'parts'        all segments as array
// ============================================================================
function tp_parse_course_shortname($shortname) {
    $shortname = trim($shortname);

    // ── Pipe-delimited format ─────────────────────────────────────────────────
    //
    // Supports variable-length shortnames with any number of middle segments.
    //
    // Rule:
    //   • If the FIRST segment is numeric  → no TYPE prefix:
    //       CODE | ... | MODE | SEMESTER
    //       coursecode = parts[0]
    //       modecode   = parts[n-2]  (always 2nd from last)
    //       semester   = parts[n-1]  (always last)
    //
    //   • Otherwise → TYPE prefix present:
    //       TYPE | CODE | ... | MODE | SEMESTER
    //       coursecode = parts[1]
    //       modecode   = parts[n-2]  (always 2nd from last)
    //       semester   = parts[n-1]  (always last)
    //
    // Examples:
    //   AIOU|8608|40|02605|60N3QL|TAXILA|SMS|ODL|2611  (9 parts, TYPE prefix)
    //   AIOU|8608|ABBOTTABAD|19|TP|ODL|2611             (7 parts, TYPE prefix)
    //   9028|G1474|16BH|ODL|2513                        (5 parts, no prefix)
    if (strpos($shortname, '|') !== false) {
        $parts = array_map('trim', explode('|', $shortname));
        $n     = count($parts);

        if (isset($parts[0]) && is_numeric($parts[0])) {
            // No-prefix format: CODE | [middle...] | ROLE | MODE | SEMESTER
            $type         = '';
            $coursecode   = $parts[0];
            $modecode     = ($n >= 2) ? $parts[$n - 2] : '';
            $semestercode = ($n >= 1) ? $parts[$n - 1] : '';
            $rolecode     = ($n >= 4) ? $parts[$n - 3] : '';
            $tail         = $shortname;
        } else {
            // Prefixed format: TYPE | CODE | [middle...] | ROLE | MODE | SEMESTER
            $type         = $parts[0];
            $tail_parts   = array_slice($parts, 1);
            $tail         = implode('|', $tail_parts);
            $coursecode   = ($n >= 2) ? $parts[1]      : '';
            $modecode     = ($n >= 3) ? $parts[$n - 2] : '';
            $semestercode = ($n >= 2) ? $parts[$n - 1] : '';
            $rolecode     = ($n >= 5) ? $parts[$n - 3] : '';
        }

        return [
            'pipe_format'  => true,
            'type'         => $type,
            'tail'         => $tail,
            'coursecode'   => $coursecode,
            'modecode'     => $modecode,
            'semestercode' => $semestercode,
            'rolecode'     => $rolecode,
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
        'rolecode'     => '',
        'parts'        => [],
    ];
}

// ============================================================================
// HELPER: Whether a parsed shortname carries the required AIOU prefix.
// Both the certificate course and the project-submission course MUST have
// this prefix (parts[0]) for the two to be considered linkable.
// ============================================================================
function tp_has_required_prefix(array $parsed) {
    return $parsed['pipe_format']
        && trim((string) $parsed['type']) !== ''
        && strcasecmp(trim($parsed['type']), TP_REQUIRED_PREFIX) === 0;
}

// ============================================================================
// HELPER: Normalised role marker (3rd-from-last segment) for a parsed
// shortname — TP_ROLE_SUBMISSION ("TP") or TP_ROLE_CERTIFICATE ("SMS").
// ============================================================================
function tp_shortname_role(array $parsed) {
    return strtoupper(trim((string) ($parsed['rolecode'] ?? '')));
}

// ============================================================================
// HELPER: Turn a course-code + semester-code pair into a safe admin_setting
// name fragment (alphanumeric + underscore only).
// ============================================================================
function tp_grading_components_setting_name($coursecode, $semestercode) {
    $code = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $coursecode);
    $sem  = preg_replace('/[^A-Za-z0-9_]/', '_', (string) $semestercode);
    return 'grading_components_' . $code . '_' . $sem;
}

// ============================================================================
// HELPER: Fetch the configured grading component names for a project-
// submission course-code + semester-code group (Site administration >
// Plugins > Activity modules > Teaching Practice).
//
// Grouping is by (coursecode, semestercode), NOT by individual course:
// every AIOU-prefixed, TP-marked course that shares the same course code
// AND the same semester code (e.g. several regional sections of "8608" in
// semester "2611") is the SAME offering with the same components, so they
// share ONE dropdown. A different semester (or a different course code)
// gets its own separate dropdown.
//
// Returns an empty array when none are configured for that group —
// callers should treat an empty array as "no filter / use every assignment
// in the course" (the original behaviour).
// ============================================================================
function tp_get_grading_components($coursecode, $semestercode) {
    if (empty($coursecode) || empty($semestercode)) {
        return [];
    }
    $settingname = tp_grading_components_setting_name($coursecode, $semestercode);
    $raw = get_config('mod_researchproject', $settingname);
    if (empty($raw)) {
        return [];
    }
    $names = preg_split('/[\r\n,]+/', $raw);
    return array_values(array_filter(array_map('trim', $names), function($n) {
        return $n !== '';
    }));
}

// ============================================================================
// HELPER: Group every AIOU-prefixed, TP-marked (project-submission) course
// site-wide by (coursecode, semestercode), and collect the distinct
// assignment names found across all courses in each group.
//
// Used to build one "Grading Components" dropdown per (coursecode,
// semestercode) group on settings.php. Regional sections sharing the same
// course code AND semester code are merged into a single dropdown (their
// components are the same); a different semester for the same code gets
// its own separate dropdown.
//
// Every matching group gets a field — even one whose courses have no
// assignments yet — so new courses always show up automatically and the
// page never looks "stuck" on whichever course happens to have an
// assignment first.
//
// @return array "coursecode|semestercode" => [
//     'coursecode' => string, 'semestercode' => string,
//     'courses' => [course records...], 'options' => [name => name, ...]
// ]
// ============================================================================
function tp_get_submission_groups_with_components() {
    global $DB;

    // Narrow the course scan to AIOU-prefixed shortnames up front.
    $like  = $DB->sql_like_escape(TP_REQUIRED_PREFIX) . '|%';
    $sql   = "SELECT id, fullname, shortname FROM {course}
               WHERE id > 1 AND " . $DB->sql_like('shortname', ':prefix', false);
    $courses = $DB->get_records_sql($sql, ['prefix' => $like], 0, 2000);

    $groups = []; // key => ['coursecode', 'semestercode', 'courses' => [...]]
    foreach ($courses as $c) {
        $parsed = tp_parse_course_shortname($c->shortname);
        if (!tp_has_required_prefix($parsed) || tp_shortname_role($parsed) !== TP_ROLE_SUBMISSION
                || empty($parsed['coursecode']) || empty($parsed['semestercode'])) {
            continue;
        }

        $key = $parsed['coursecode'] . '|' . $parsed['semestercode'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'coursecode'   => $parsed['coursecode'],
                'semestercode' => $parsed['semestercode'],
                'courses'      => [],
            ];
        }
        $groups[$key]['courses'][] = $c;
    }

    $result = [];
    foreach ($groups as $key => $group) {
        $courseids = array_map(function($c) {
            return $c->id;
        }, $group['courses']);

        list($insql, $params) = $DB->get_in_or_equal($courseids);
        $names = $DB->get_fieldset_select('assign', 'DISTINCT name', "course $insql", $params);
        $names = array_unique(array_map('trim', $names));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        // Always include the group, even with an empty option list.
        $group['options'] = empty($names) ? [] : array_combine($names, $names);
        $result[$key] = $group;
    }

    uksort($result, 'strnatcasecmp');
    return $result;
}

// ============================================================================
// HELPER: Find the matching linked Course based on current course shortname.
//
// Linking rule (pipe-delimited shortnames AIOU|COURSECODE|...|ROLE|MODE|SEMESTER):
//   Extracts coursecode   = segment[1]
//   Extracts rolecode     = segment[n-3]  (TP = submission course, SMS = certificate course)
//   Extracts modecode     = segment[n-2]
//   Extracts semestercode = segment[n-1]
//
//   Both the current course and the matched course MUST carry the AIOU prefix.
//   Finds the project-submission (ROLE = TP) course with the SAME coursecode,
//   SAME mode, and SAME semester.
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

    $parsed = tp_parse_course_shortname($current_course_shortname);

    // Hard requirement: only AIOU-prefixed courses participate in this link.
    if (!tp_has_required_prefix($parsed) || empty($parsed['coursecode'])) {
        $cache[$cache_key] = null;
        return null;
    }

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

    // Strategies in decreasing strictness: code+mode+semester, code+semester, code-only.
    $strategies = [];
    if (!empty($modecode) && !empty($semestercode)) {
        $strategies[] = 'code_mode_sem';
    }
    if (!empty($semestercode)) {
        $strategies[] = 'code_sem';
    }
    $strategies[] = 'code_only';

    foreach ($strategies as $strategy) {
        $like_code = '%' . $DB->sql_like_escape($coursecode) . '%';
        $params    = array_merge($exclude_params, $enrol_params, ['code_like' => $like_code]);
        $sem_sql   = '';

        if ($strategy !== 'code_only') {
            $like_sem           = '%' . $DB->sql_like_escape($semestercode) . '%';
            $params['sem_like'] = $like_sem;
            $sem_sql            = ' AND ' . $DB->sql_like('c.shortname', ':sem_like', false);
        }

        $sql = "SELECT c.id, c.fullname, c.shortname
                  FROM {course} c
                  $enrol_join
                 WHERE c.id > 1
                   AND " . $DB->sql_like('c.shortname', ':code_like', false) . "
                   $sem_sql
                   $exclude_sql
                 ORDER BY c.id ASC";

        $candidates = $DB->get_records_sql($sql, $params, 0, 30);

        $submission_matches = [];
        $other_aiou_matches = [];

        foreach ($candidates as $c) {
            $cp = tp_parse_course_shortname($c->shortname);

            // Candidate must also carry the AIOU prefix.
            if (!tp_has_required_prefix($cp)) {
                continue;
            }
            if (strcasecmp($cp['coursecode'], $coursecode) !== 0) {
                continue;
            }
            if ($strategy === 'code_mode_sem') {
                if (!empty($modecode) && !empty($cp['modecode']) && strcasecmp($cp['modecode'], $modecode) !== 0) {
                    continue;
                }
                if (!empty($semestercode) && !empty($cp['semestercode']) && strcasecmp($cp['semestercode'], $semestercode) !== 0) {
                    continue;
                }
            } else if ($strategy === 'code_sem') {
                if (!empty($semestercode) && !empty($cp['semestercode']) && strcasecmp($cp['semestercode'], $semestercode) !== 0) {
                    continue;
                }
            }

            // Prefer the project-submission (TP) course over any other AIOU match
            // (e.g. a sibling certificate/SMS course sharing the same code).
            if (tp_shortname_role($cp) === TP_ROLE_SUBMISSION) {
                $submission_matches[] = $c;
            } else {
                $other_aiou_matches[] = $c;
            }
        }

        $result = !empty($submission_matches) ? reset($submission_matches)
                : (!empty($other_aiou_matches) ? reset($other_aiou_matches) : null);

        if ($result) {
            $cache[$cache_key] = $result;
            return $result;
        }
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
    $keywords = ['teaching practice', 'teachingpractice', 'project', 'submission', 'assignment', 'research project', 'researchproject'];
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
// HELPER: Find the per-student project-submission course.
//
// STRICT mode: ONLY returns courses whose shortname carries the AIOU prefix
// AND whose ROLE marker (3rd-from-last segment) is exactly "TP"
// (case-insensitive). No fallback to other course types. A course that
// matches coursecode + mode + semester but lacks the AIOU prefix or TP
// marker will NOT be linked — it will appear as a suggestion instead.
//
// Rules:
//   1. Filters to only courses the specific student is actively enrolled in.
//   2. Shortname TYPE prefix (parts[0]) MUST be "AIOU".
//   3. Shortname ROLE marker (parts[n-3]) MUST be "TP".
//   4. Matches on BOTH shortname segments AND the course idnumber field
//      for the course code (idnumber has no role marker of its own).
//   5. coursecode = parts[1], modecode = parts[n-2], semestercode = parts[n-1].
//
// @param string $cert_shortname  Shortname of the teaching practice (certificate) course.
// @param int    $cert_course_id  ID of the certificate course (excluded from results).
// @param int    $studentid       User ID whose enrolments are checked.
// @return stdClass|null          The matched project-submission course, or null.
// ============================================================================
function tp_find_student_submission_course($cert_shortname, $cert_course_id, $studentid) {
    global $DB;

    // Per-student, per-request cache.
    static $cache = [];
    $cache_key = $cert_shortname . '|excl:' . $cert_course_id . '|s:' . $studentid;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    $parsed = tp_parse_course_shortname($cert_shortname);

    // Hard requirement: the certificate course itself must carry the AIOU prefix.
    if (!tp_has_required_prefix($parsed) || empty($parsed['coursecode'])) {
        $cache[$cache_key] = null;
        return null;
    }

    $coursecode   = $parsed['coursecode'];
    $modecode     = $parsed['modecode'];
    $semestercode = $parsed['semestercode'];

    // ── Enrollment join: only this student's active enrolments ────────────────
    $enrol_join = "JOIN {enrol}            e  ON e.courseid  = c.id AND e.status = 0
                   JOIN {user_enrolments}  ue ON ue.enrolid  = e.id
                                             AND ue.userid   = :enrol_userid
                                             AND ue.status   = 0";

    $exclude_sql = $cert_course_id ? ' AND c.id != :excludeid' : '';

    // ── Build LIKE patterns for shortname AND idnumber ────────────────────────
    $like_code = '%' . $DB->sql_like_escape($coursecode) . '%';
    $params = [
        'enrol_userid' => $studentid,
        'code_sn'      => $like_code,
        'code_id'      => $like_code,
    ];
    if ($cert_course_id) {
        $params['excludeid'] = $cert_course_id;
    }

    // Optional semester clause applied to both shortname and idnumber.
    $sem_clause = '';
    if (!empty($semestercode)) {
        $like_sem         = '%' . $DB->sql_like_escape($semestercode) . '%';
        $params['sem_sn'] = $like_sem;
        $params['sem_id'] = $like_sem;
        $sem_clause = " AND (
            " . $DB->sql_like('c.shortname', ':sem_sn', false) . "
            OR  " . $DB->sql_like('c.idnumber', ':sem_id', false) . "
        )";
    }

    // Fetch candidates: coursecode present in shortname OR idnumber.
    $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber
              FROM {course} c
              $enrol_join
             WHERE c.id > 1
               AND (
                   " . $DB->sql_like('c.shortname', ':code_sn', false) . "
                   OR " . $DB->sql_like('c.idnumber', ':code_id', false) . "
               )
               $sem_clause
               $exclude_sql
             ORDER BY c.id ASC";

    $candidates = $DB->get_records_sql($sql, $params, 0, 30);

    foreach ($candidates as $c) {
        $cp = tp_parse_course_shortname($c->shortname);

        // STRICT: AIOU prefix is mandatory — skip everything else.
        if (!tp_has_required_prefix($cp)) {
            continue;
        }

        // STRICT: ROLE marker must identify this as the submission (TP) course.
        if (tp_shortname_role($cp) !== TP_ROLE_SUBMISSION) {
            continue;
        }

        // Coursecode must match via shortname segment OR idnumber.
        $code_ok = strcasecmp($cp['coursecode'], $coursecode) === 0
                || (trim($c->idnumber) !== '' && strcasecmp(trim($c->idnumber), $coursecode) === 0);
        if (!$code_ok) {
            continue;
        }

        // Mode must match when both sides are non-empty.
        if (!empty($modecode) && !empty($cp['modecode']) && strcasecmp($cp['modecode'], $modecode) !== 0) {
            continue;
        }

        // Semester must match when both sides are non-empty.
        if (!empty($semestercode) && !empty($cp['semestercode']) && strcasecmp($cp['semestercode'], $semestercode) !== 0) {
            continue;
        }

        // All checks passed — this is the student's project-submission course.
        $cache[$cache_key] = $c;
        return $c;
    }

    // No matching project-submission course found for this student.
    $cache[$cache_key] = null;
    return null;
}

// ============================================================================
// HELPER: Find ALL courses that match the certificate course's format criteria
// regardless of their ROLE marker, and without filtering by student enrolment.
//
// Used exclusively for the ADMIN SUGGESTION PANEL shown when no project-
// submission (TP) course is automatically linked. Returns every AIOU-prefixed
// course whose coursecode + modecode + semestercode matches — so the admin can
// see what exists and decide whether to correct a course's ROLE marker.
//
// Each result entry contains:
//   'course'         stdClass  — the Moodle course record (id, fullname, shortname)
//   'is_submission'  bool      — true if the ROLE marker is already "TP"
//   'rolecode'       string    — the detected ROLE marker (e.g. "TP", "SMS", "")
//   'via_idnumber'   bool      — true if match was via idnumber rather than shortname
//
// @param string $cert_shortname  Shortname of the certificate course.
// @param int    $exclude_id      Course ID to exclude (the certificate course itself).
// @return array  Sorted array of suggestion entries (may be empty).
// ============================================================================
function tp_find_submission_course_suggestions($cert_shortname, $exclude_id = 0) {
    global $DB;

    $parsed       = tp_parse_course_shortname($cert_shortname);
    $coursecode   = $parsed['coursecode'];
    $modecode     = $parsed['modecode'];
    $semestercode = $parsed['semestercode'];

    if (empty($coursecode)) {
        return [];
    }

    $exclude_sql = $exclude_id ? ' AND c.id != :excludeid' : '';
    $params = [];
    if ($exclude_id) {
        $params['excludeid'] = $exclude_id;
    }

    $like_code    = '%' . $DB->sql_like_escape($coursecode) . '%';
    $params['cs'] = $like_code;
    $params['ci'] = $like_code;

    $sem_clause = '';
    if (!empty($semestercode)) {
        $like_sem    = '%' . $DB->sql_like_escape($semestercode) . '%';
        $params['ss'] = $like_sem;
        $params['si'] = $like_sem;
        $sem_clause = " AND (
            " . $DB->sql_like('c.shortname', ':ss', false) . "
            OR  " . $DB->sql_like('c.idnumber', ':si', false) . "
        )";
    }

    $sql = "SELECT c.id, c.fullname, c.shortname, c.idnumber
              FROM {course} c
             WHERE c.id > 1
               AND (
                   " . $DB->sql_like('c.shortname', ':cs', false) . "
                   OR " . $DB->sql_like('c.idnumber', ':ci', false) . "
               )
               $sem_clause
               $exclude_sql
             ORDER BY c.shortname ASC";

    $candidates = $DB->get_records_sql($sql, $params, 0, 20);

    $suggestions = [];
    foreach ($candidates as $c) {
        $cp = tp_parse_course_shortname($c->shortname);

        // Must carry the AIOU prefix to even be a candidate under the new scheme.
        if (!tp_has_required_prefix($cp)) {
            continue;
        }

        // Coursecode check: via shortname segment OR idnumber.
        $code_via_sn = strcasecmp($cp['coursecode'], $coursecode) === 0;
        $code_via_id = (trim($c->idnumber) !== '' && strcasecmp(trim($c->idnumber), $coursecode) === 0);
        if (!$code_via_sn && !$code_via_id) {
            continue;
        }

        // Mode check (skip when either side is empty).
        if (!empty($modecode) && !empty($cp['modecode']) && strcasecmp($cp['modecode'], $modecode) !== 0) {
            continue;
        }

        // Semester check (skip when either side is empty).
        if (!empty($semestercode) && !empty($cp['semestercode']) && strcasecmp($cp['semestercode'], $semestercode) !== 0) {
            continue;
        }

        $suggestions[] = [
            'course'        => $c,
            'is_submission' => (tp_shortname_role($cp) === TP_ROLE_SUBMISSION),
            'rolecode'      => $cp['rolecode'],
            'via_idnumber'  => (!$code_via_sn && $code_via_id),
        ];
    }

    return $suggestions;
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
    $sql = "SELECT ag.grade
              FROM {assign_grades} ag
             WHERE ag.assignment = :assignid
               AND ag.userid     = :userid
               AND ag.grade      IS NOT NULL
               AND ag.grade      >= 0
          ORDER BY ag.attemptnumber DESC, ag.id DESC";

    $grade = $DB->get_record_sql($sql, [
        'assignid' => $assignmentid,
        'userid'   => $studentid,
    ], IGNORE_MULTIPLE);

    if (!$grade || $grade->grade === null || $grade->grade < 0) {
        return false;
    }

    return true;
}

// ============================================================================
// HELPER: Resolve which assignment(s) in a course count as the configured
// grading "component(s)" — the same name-matching rule used for the grade
// percentage calculation. Falls back to tp_find_matching_assignment()'s
// single best-guess assignment when no components are configured.
// ============================================================================
function tp_find_component_assignments($courseid, array $componentnames = []) {
    global $DB;

    $assigns = $DB->get_records('assign', ['course' => $courseid], 'id ASC');
    if (empty($assigns)) {
        return [];
    }

    if (!empty($componentnames)) {
        $wanted = array_map(function($n) {
            return strtolower(trim($n));
        }, $componentnames);

        $matched = array_values(array_filter($assigns, function($a) use ($wanted) {
            return in_array(strtolower(trim($a->name)), $wanted, true);
        }));

        if (!empty($matched)) {
            return $matched;
        }
        // None of the configured names exist in this course (e.g. a
        // differently-structured regional copy) — fall through to the
        // single best-guess assignment below.
    }

    $single = tp_find_matching_assignment($courseid);
    return $single ? [$single] : [];
}

// ============================================================================
// HELPER: Determine a student's submission/grading status against the
// configured grading component(s) in a course — this is what drives the
// "Project Submitted" badge the Cooperating Teacher and Head Teacher see on
// the evaluation dashboard, so it reflects whichever assignment(s) the
// admin selected under "Grading Components" (Site administration > Plugins
// > Activity modules > Teaching Practice), not just any assignment.
//
// Status rules across the resolved component assignment(s):
//   'not_submitted' — no submission on any of them
//   'submitted'     — at least one submission, but not all submitted+graded
//   'graded'        — every component assignment is submitted AND graded
//
// @return string 'not_submitted' | 'submitted' | 'graded'
// ============================================================================
function tp_get_component_submission_status($studentid, $courseid, array $componentnames = []) {
    $assigns = tp_find_component_assignments($courseid, $componentnames);
    if (empty($assigns)) {
        return 'not_submitted';
    }

    global $DB;

    $any_submitted = false;
    $all_graded    = true;

    foreach ($assigns as $a) {
        $submission = $DB->get_record('assign_submission', [
            'assignment' => $a->id,
            'userid'     => $studentid,
            'latest'     => 1,
        ]);

        if ($submission && in_array($submission->status, ['submitted', 'graded'])) {
            $any_submitted = true;
        } else {
            $all_graded = false;
            continue;
        }

        if (!tp_is_assignment_submitted_and_graded($studentid, $a->id)) {
            $all_graded = false;
        }
    }

    if (!$any_submitted) {
        return 'not_submitted';
    }
    return $all_graded ? 'graded' : 'submitted';
}

// ============================================================================
// HELPER: Compute student's overall grade percentage across the configured
//         grading component(s) in a given course.
//
// Calculation: (sum of marks obtained) / (sum of max marks) * 100
// This treats all assignments by their raw marks rather than averaging
// individual percentages, which avoids bias when assignments have different
// max marks.
//
// @param int   $studentid      The student's user id.
// @param int   $courseid       The project-submission course id.
// @param array $componentnames Optional. Assignment names (as configured on
//                               the activity's "Grading Components" setting)
//                               to restrict the calculation to. When empty,
//                               ALL assignments in the course are used
//                               (single component selected = that component
//                               only; multiple selected = those components).
//
// Only assignments that have been graded (grade >= 0) are included.
// Returns null if no assignments have been graded yet.
// ============================================================================
function tp_get_student_average_grade_percentage($studentid, $courseid, array $componentnames = []) {
    global $DB;

    // Fetch every gradeable assignment in this course.
    $assigns = $DB->get_records('assign', ['course' => $courseid], 'id ASC');
    if (empty($assigns)) {
        return null;
    }

    // Restrict to the configured grading component(s), matched by name.
    if (!empty($componentnames)) {
        $wanted = array_map(function($n) {
            return strtolower(trim($n));
        }, $componentnames);

        $filtered = array_filter($assigns, function($a) use ($wanted) {
            return in_array(strtolower(trim($a->name)), $wanted, true);
        });

        // Only apply the filter if at least one configured component actually
        // exists in this course — otherwise fall back to all assignments
        // (e.g. a differently-structured regional copy of the course).
        if (!empty($filtered)) {
            $assigns = $filtered;
        }
    }

    $total_obtained = 0.0;   // Sum of marks obtained across all graded assignments.
    $total_maxmarks = 0.0;   // Sum of max marks across those same assignments.
    $graded_count   = 0;

    foreach ($assigns as $assign) {
        // Skip assignments with no max grade defined.
        if (empty($assign->grade) || $assign->grade <= 0) {
            continue;
        }

        // Get the student's LATEST graded attempt for this assignment.
        // There can be multiple rows in assign_grades (one per attempt).
        // We want the one with the highest attemptnumber that has a real grade.
        $sql = "SELECT ag.grade
                  FROM {assign_grades} ag
                 WHERE ag.assignment = :assignid
                   AND ag.userid     = :userid
                   AND ag.grade      IS NOT NULL
                   AND ag.grade      >= 0
              ORDER BY ag.attemptnumber DESC, ag.id DESC";

        $grade_rec = $DB->get_record_sql($sql, [
            'assignid' => $assign->id,
            'userid'   => $studentid,
        ], IGNORE_MULTIPLE);

        // Skip if no valid grade found.
        if (!$grade_rec || $grade_rec->grade === null || $grade_rec->grade < 0) {
            continue;
        }

        $total_obtained += (float) $grade_rec->grade;
        $total_maxmarks += (float) $assign->grade;
        $graded_count++;
    }

    if ($graded_count === 0 || $total_maxmarks <= 0) {
        return null; // No graded assignments yet - cannot make a decision.
    }

    // Return overall percentage: total marks obtained / total max marks * 100.
    return round(($total_obtained / $total_maxmarks) * 100.0, 2);
}

// ============================================================================
// HELPER: Fetch configured passing percentage for certificate eligibility
// Defaults to 50% if not configured in plugin settings.
// ============================================================================
function tp_get_passing_percentage() {
    $val = get_config('mod_researchproject', 'passing_percentage');
    if ($val === false || $val === null || $val === '') {
        return 50;
    }
    return (float) $val;
}

// ============================================================================
// HELPER: Whether the certificate should stay gated behind project
// submission + a passing grade (Site administration > Plugins > Activity
// modules > Teaching Practice > "Require Project Submission & Passing
// Grade"). Defaults to ON (true) — the plugin's original behaviour.
//
// When OFF, the certificate becomes available as soon as both the CT and
// HT evaluations are complete, regardless of whether the student has
// submitted or passed the linked project-submission course.
// ============================================================================
function tp_requires_grading_before_certificate() {
    $val = get_config('mod_researchproject', 'require_submission_grading');
    if ($val === false || $val === null || $val === '') {
        return true;
    }
    return (bool) $val;
}

// ============================================================================
// HELPER: Returns true if the student's average grade across all assignments
//         in a course meets or exceeds the configured passing percentage.
//
// Returns: true  -> passed
//          false -> failed or not yet graded
// ============================================================================
function tp_student_passes_course($studentid, $courseid, array $componentnames = []) {
    $avg = tp_get_student_average_grade_percentage($studentid, $courseid, $componentnames);
    if ($avg === null) {
        return false; // Not graded yet - treat as not passed.
    }
    return $avg >= tp_get_passing_percentage();
}

/**
 * Serves the files stored in the teaching practice component (e.g. signature images).
 */
function mod_researchproject_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
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

    $file = $fs->get_file($context->id, 'mod_researchproject', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function mod_teachingpractice_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    return mod_researchproject_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}