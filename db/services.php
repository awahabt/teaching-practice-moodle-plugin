<?php
/**
 * db/services.php
 *
 * Registers the external AJAX functions so Moodle knows about them.
 * These are called from JavaScript (AMD modules) in the mod_form.
 */
defined('MOODLE_INTERNAL') || die();

$functions = [

    // Called by course_selector.js autocomplete to search courses
    'mod_teachingpractice_search_courses' => [
        'classname'   => 'mod_teachingpractice\external\search_courses',
        'methodname'  => 'search_courses',
        'description' => 'Search for courses by name or shortname',
        'type'        => 'read',
        'ajax'        => true,          // Allows calling from JavaScript
        'loginrequired' => true,
    ],

    // Called by mod_form.js to populate assignment dropdown
    'mod_teachingpractice_get_assignments' => [
        'classname'   => 'mod_teachingpractice\external\get_assignments',
        'methodname'  => 'get_assignments',
        'description' => 'Get assignments for a given course',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
