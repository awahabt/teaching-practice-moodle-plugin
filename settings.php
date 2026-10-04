<?php
/**
 * settings.php
 *
 * Site-wide administration settings for mod_researchproject.
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/researchproject/lib.php');
require_once($CFG->dirroot . '/mod/researchproject/classes/admin_setting_grading_components.php');

if ($ADMIN->fulltree) {
    // Load all Moodle roles.
    $all_roles = role_get_names(null, ROLENAME_ORIGINAL);
    $role_options = array('' => '--- Select role ---');
    foreach ($all_roles as $role) {
        $role_options[$role->id] = $role->localname;
    }

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/student_role',
        get_string('student_role', 'mod_researchproject'),
        get_string('student_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/ct_role',
        get_string('ct_role', 'mod_researchproject'),
        get_string('ct_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/ht_role',
        get_string('ht_role', 'mod_researchproject'),
        get_string('ht_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    // Signature configuration section.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/signature_heading_wrapper',
        get_string('signature_heading', 'mod_researchproject'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/signature_name',
        get_string('signature_name', 'mod_researchproject'),
        get_string('signature_name_help', 'mod_researchproject'),
        'Prof. Dr. Ali Ahmed',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/signature_title',
        get_string('signature_title', 'mod_researchproject'),
        get_string('signature_title_help', 'mod_researchproject'),
        'Chairman / Department Head',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configstoredfile(
        'mod_researchproject/signature_image',
        get_string('signature_image', 'mod_researchproject'),
        get_string('signature_image_help', 'mod_researchproject'),
        'signature_image'
    ));

    // Course Grade / Passing Criteria section.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/passing_heading_wrapper',
        get_string('passing_heading', 'mod_researchproject'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/passing_percentage',
        get_string('passing_percentage', 'mod_researchproject'),
        get_string('passing_percentage_help', 'mod_researchproject'),
        50,
        PARAM_INT
    ));

    // Master switch: when unticked, the certificate is no longer gated behind
    // project submission + a passing grade — it becomes available as soon as
    // both the CT and HT evaluations are complete. Ticked (default) preserves
    // the original behaviour.
    $settings->add(new admin_setting_configcheckbox(
        'mod_researchproject/require_submission_grading',
        get_string('require_submission_grading', 'mod_researchproject'),
        get_string('require_submission_grading_help', 'mod_researchproject'),
        1
    ));

    // ── Grading Components — one dropdown PER (course code + semester code) ──
    // group of project-submission (TP) courses. A course qualifies when its
    // shortname has the AIOU prefix, the same course code (2nd segment), the
    // TP role marker, the ODL mode, and a semester code — all AIOU-prefixed,
    // TP-marked courses that share BOTH the same course code AND the same
    // semester code are the same offering, so they share ONE dropdown here;
    // a different course code (or a different semester) always gets its own
    // separate dropdown. Searchable, chip-style multi-select — same widget as
    // the "Subjects Taught" field on the Head Teacher evaluation form. Pick
    // one component (checks that component only) or several (averages across
    // just those). Leave a group's field empty to use all of its assignments.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/grading_components_heading_wrapper',
        get_string('grading_components_heading', 'mod_researchproject'),
        get_string('grading_components_heading_desc', 'mod_researchproject')
    ));

    $submission_groups = tp_get_submission_groups_with_components();

    if (empty($submission_groups)) {
        $settings->add(new admin_setting_description(
            'mod_researchproject/grading_components_empty',
            '',
            get_string('grading_components_none_found', 'mod_researchproject')
        ));
    } else {
        foreach ($submission_groups as $key => $group) {
            $settingname = tp_grading_components_setting_name($group['coursecode'], $group['semestercode']);
            $label = get_string('grading_components_group_label', 'mod_researchproject', (object) [
                'coursecode'   => $group['coursecode'],
                'semestercode' => $group['semestercode'],
            ]);

            $help = get_string('grading_components_help', 'mod_researchproject');
            if (empty($group['options'])) {
                $help .= ' ' . get_string('grading_components_group_empty', 'mod_researchproject');
            }

            $settings->add(new mod_researchproject_admin_setting_grading_components(
                'mod_researchproject/' . $settingname,
                get_string('grading_components_for_course', 'mod_researchproject', $label),
                $help,
                [],
                $group['options']
            ));
        }
    }
}
