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
    return $map[$value] ?? '—';
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
    return $map[$value] ?? '—';
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
    $data = new stdClass();
    tp_merge_section_a_data($data, tp_fetch_section_a_from_profile($student));
    tp_merge_section_a_data($data, tp_fetch_section_a_from_assignment($student->id, $instance->linked_assign));
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
        return '—';
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
        return !empty($value) ? s($value) : '—';
    };

    $startdate = tp_format_certificate_date($data->start_date ?? 0);
    $enddate   = tp_format_certificate_date($data->end_date ?? 0);
    $days      = !empty($data->days_count) ? (int) $data->days_count : '—';

    $course_shortname = '';
    if (!empty($data->instanceid)) {
        $tp = $DB->get_record('teachingpractice', ['id' => $data->instanceid]);
        if ($tp) {
            $c = $DB->get_record('course', ['id' => $tp->course]);
            if ($c) {
                $course_shortname = $c->shortname;
            }
        }
    }
    if (empty($course_shortname)) {
        $course_shortname = $COURSE->shortname;
    }

    $html = html_writer::start_div('tp-section-a-cert');
    $html .= html_writer::tag('div', get_string('certno_label', 'mod_teachingpractice') . ' ' .
        html_writer::tag('strong', s($certno)), ['class' => 'tp-cert-no']);
    $html .= html_writer::tag('div', get_string('certificate_title', 'mod_teachingpractice'),
        ['class' => 'tp-cert-title']);

    $html .= html_writer::start_div('tp-cert-body');
    $html .= html_writer::tag('p',
        'This is to certify that Mr./Ms./Mrs. ' .
        html_writer::tag('strong', fullname($student)) . ', Registration No. ' .
        html_writer::tag('strong', s($student->username)) .
        ', has successfully completed the Teaching Practice (Course Code: ' . s($course_shortname) . ') at ' .
        html_writer::tag('strong', $val($data->school_name ?? '')) .
        ' from ' . html_writer::tag('strong', $startdate) .
        ' to ' . html_writer::tag('strong', $enddate) .
        ', with timings ' .
        html_writer::tag('strong', $val($data->morning_time ?? '') . ' – ' . $val($data->afternoon_time ?? '')) .
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
            border: 3px double #1a3a6b;
            padding: 24px 28px;
            margin: 0 0 20px;
            font-family: "Times New Roman", Times, serif;
            background: #fff;
            color: #1a1a1a;
        }
        .tp-section-a-cert .tp-cert-no { text-align: right; font-size: 0.9rem; color: #666; margin-bottom: 8px; }
        .tp-section-a-cert .tp-cert-title {
            text-align: center; font-size: 1.25rem; font-weight: bold; color: #1a3a6b;
            text-transform: uppercase; letter-spacing: 1px; margin: 12px 0 18px;
            text-decoration: underline;
        }
        .tp-section-a-cert .tp-cert-body { font-size: 1rem; line-height: 1.85; text-align: justify; }
        .tp-section-a-cert .tp-cert-body strong { color: #1a3a6b; }
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

    if (!empty($instance->linked_assign)) {
        $submittedids = $DB->get_fieldset_sql(
            "SELECT DISTINCT s.userid
               FROM {assign_submission} s
              WHERE s.assignment = :assignid
                AND s.latest = 1
                AND s.status IN ('submitted', 'graded')",
            ['assignid' => $instance->linked_assign]
        );
        foreach ($submittedids as $userid) {
            $studentids[$userid] = $userid;
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
