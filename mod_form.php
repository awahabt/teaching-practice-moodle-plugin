<?php
/**
 * mod_form.php
 *
 * Activity settings form shown when teacher adds or edits this activity.
 * The linked course and assignment are resolved automatically from the
 * course shortname at runtime — no manual configuration needed.
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_teachingpractice_mod_form extends moodleform_mod {

    public function definition() {
        global $DB, $PAGE;

        $mform = $this->_form;

        // ── Activity name ─────────────────────────────────────────────────────
        $mform->addElement('text', 'name', get_string('modulename', 'mod_teachingpractice'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', 'Teaching Practice');

        // ── Description ───────────────────────────────────────────────────────
        $this->standard_intro_elements();

        // Note: Linked course and assignment are resolved automatically from the
        // course shortname format: TYPE|COURSECODE|GROUP|BATCH|MODE|SEMESTER

        // ── Standard Moodle fields ────────────────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
