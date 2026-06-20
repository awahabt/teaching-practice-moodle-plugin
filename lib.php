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

    // Priority: HT > CT > Student (in case someone has multiple roles)
    if ($instance->ht_role && in_array((int)$instance->ht_role, $user_role_ids)) {
        return 'ht';
    }
    if ($instance->ct_role && in_array((int)$instance->ct_role, $user_role_ids)) {
        return 'ct';
    }
    if ($instance->student_role && in_array((int)$instance->student_role, $user_role_ids)) {
        return 'student';
    }

    return 'unknown';
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
