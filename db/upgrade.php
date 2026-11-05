<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_researchproject_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 20251003014) {
        // Add grades_taught (JSON array of grades selected by the Head Teacher).
        $table = new xmldb_table('researchproject_performa');
        $field = new xmldb_field('grades_taught', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'subject_3');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 20251003014, 'researchproject');
    }

    if ($oldversion < 20251003016) {
        // Add grade_subjects (JSON map of grade => subjects taught in that grade).
        $table = new xmldb_table('researchproject_performa');
        $field = new xmldb_field('grade_subjects', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grades_taught');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 20251003016, 'researchproject');
    }

    return true;
}
