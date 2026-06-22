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
}
