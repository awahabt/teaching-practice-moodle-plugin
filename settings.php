<?php
/**
 * settings.php
 *
 * Site-wide administration settings for mod_researchproject.
 */
defined('MOODLE_INTERNAL') || die();

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
}
