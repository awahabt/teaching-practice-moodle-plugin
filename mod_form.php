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

class mod_researchproject_mod_form extends moodleform_mod {

    public function definition() {
        global $DB, $PAGE;

        $mform = $this->_form;

        // ── Activity name ─────────────────────────────────────────────────────
        $mform->addElement('text', 'name', get_string('modulename', 'mod_researchproject'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', 'Teaching Practice');

        // ── Description ───────────────────────────────────────────────────────
        $this->standard_intro_elements();

        // Note: The linked project-submission course and assignment(s) are resolved
        // automatically from the course shortname format:
        //   AIOU|COURSECODE|...|ROLE|MODE|SEMESTER  (ROLE: TP = submission, SMS = certificate)
        //
        // Which assignment(s) count towards the passing-grade calculation is
        // configured site-wide under Site administration > Plugins > Activity
        // modules > Teaching Practice (see settings.php), not here.

        // ── Standard Moodle fields ────────────────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
