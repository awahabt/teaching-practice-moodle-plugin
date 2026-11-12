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

    if ($oldversion < 20251003017) {
        // CT / HT system-improvement feedback.
        $table = new xmldb_table('researchproject_feedback');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('role', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('responses', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'researchproject', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('instance_user_role', XMLDB_INDEX_UNIQUE, ['instanceid', 'userid', 'role']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 20251003017, 'researchproject');
    }

    if ($oldversion < 20251003019) {
        // Feedback semester is now worked out from the date; drop the old setting.
        unset_config('feedback_semester', 'mod_researchproject');
        upgrade_mod_savepoint(true, 20251003019, 'researchproject');
    }

    if ($oldversion < 20251003022) {
        // Safety net: sites that were already past 20251003017 (e.g. upgraded
        // with an earlier copy of the code) never ran the step above, so the
        // feedback table may be missing. Create it if so — no-op otherwise.
        $table = new xmldb_table('researchproject_feedback');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('role', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('responses', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('instanceid', XMLDB_KEY_FOREIGN, ['instanceid'], 'researchproject', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('instance_user_role', XMLDB_INDEX_UNIQUE, ['instanceid', 'userid', 'role']);
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 20251003022, 'researchproject');
    }

    return true;
}
