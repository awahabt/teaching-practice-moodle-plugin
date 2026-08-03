<?php
/**
 * settings.php
 *
 * Site-wide administration settings for mod_teachingpractice.
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
        'mod_teachingpractice/student_role',
        get_string('student_role', 'mod_teachingpractice'),
        get_string('student_role_help', 'mod_teachingpractice'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_teachingpractice/ct_role',
        get_string('ct_role', 'mod_teachingpractice'),
        get_string('ct_role_help', 'mod_teachingpractice'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_teachingpractice/ht_role',
        get_string('ht_role', 'mod_teachingpractice'),
        get_string('ht_role_help', 'mod_teachingpractice'),
        '',
        $role_options
    ));

    // Signature configuration section.
    $settings->add(new admin_setting_heading(
        'mod_teachingpractice/signature_heading_wrapper',
        get_string('signature_heading', 'mod_teachingpractice'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_teachingpractice/signature_name',
        get_string('signature_name', 'mod_teachingpractice'),
        get_string('signature_name_help', 'mod_teachingpractice'),
        'Prof. Dr. Ali Ahmed',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_teachingpractice/signature_title',
        get_string('signature_title', 'mod_teachingpractice'),
        get_string('signature_title_help', 'mod_teachingpractice'),
        'Chairman / Department Head',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configstoredfile(
        'mod_teachingpractice/signature_image',
        get_string('signature_image', 'mod_teachingpractice'),
        get_string('signature_image_help', 'mod_teachingpractice'),
        'signature_image',
        0,
        ['maxfiles' => 1, 'accepted_types' => ['.jpg', '.png', '.jpeg']]
    ));
}
