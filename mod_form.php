<?php
/**
 * mod_form.php
 *
 * Activity settings form shown when teacher adds or edits this activity.
 *
 * Linked Course field uses Moodle's built-in 'autocomplete' form element
 * with all courses pre-loaded as options. This gives a dropdown with a
 * built-in search/filter box — no AJAX needed, works with 1500+ courses.
 *
 * When a course is selected, the assignment dropdown reloads via JS + AJAX.
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

        // =====================================================================
        // SECTION: Linked Course
        // Uses Moodle autocomplete element — all courses loaded as options.
        // The element renders as a dropdown with a built-in search box.
        // =====================================================================
        $mform->addElement('header', 'course_heading',
            get_string('course_heading', 'mod_teachingpractice'));
        $mform->setExpanded('course_heading', true);

        // Load ALL courses into an array (id => "Full Name (SHORTNAME)")
        // Exclude site course (id = 1)
        $all_courses = $DB->get_records_select(
            'course',
            'id > 1',
            [],
            'fullname ASC',
            'id, fullname, shortname'
        );

        $course_options = [];
        foreach ($all_courses as $c) {
            $course_options[$c->id] = $c->fullname . ' (' . $c->shortname . ')';
        }

        // Moodle autocomplete element:
        //   - Renders as a styled dropdown
        //   - Has a built-in search/filter box at the top
        //   - User types to filter, then clicks to select
        //   - No AJAX needed — all options already in the page
        $mform->addElement(
            'autocomplete',
            'linked_course',
            get_string('linked_course', 'mod_teachingpractice'),
            $course_options,   // All courses passed directly as options array
            [
                'multiple'          => false,
                'noselectionstring' => '--- Search and select a course ---',
                'placeholder'       => 'Type to search courses...',
            ]
        );
        $mform->addRule('linked_course',
            'Please select the linked project submission course.', 'required', null, 'client');
        $mform->addHelpButton('linked_course', 'linked_course', 'mod_teachingpractice');

        // ── Assignment dropdown ───────────────────────────────────────────────
        // Pre-loaded if editing an existing instance.
        // When user changes the course selection, JS reloads this via AJAX.
        $assignment_options = ['' => '--- Select linked course first, then choose assignment ---'];

        $current_linked = 0;
        if (!empty($this->current->linked_course)) {
            $current_linked = (int) $this->current->linked_course;
        }

        if ($current_linked > 0) {
            $assigns = $DB->get_records(
                'assign', ['course' => $current_linked], 'name ASC', 'id, name'
            );
            if ($assigns) {
                $assignment_options = ['' => '--- Select assignment ---'];
                foreach ($assigns as $a) {
                    $assignment_options[$a->id] = $a->name;
                }
            } else {
                $assignment_options = ['' => '--- No assignments found in selected course ---'];
            }
        }

        $mform->addElement('select', 'linked_assign',
            get_string('linked_assign', 'mod_teachingpractice'),
            $assignment_options);
        $mform->setType('linked_assign', PARAM_INT);
        $mform->addRule('linked_assign',
            'Please select the project submission assignment.', 'required', null, 'client');
        $mform->addHelpButton('linked_assign', 'linked_assign', 'mod_teachingpractice');

        // Roles configuration moved to global administration settings.

        // ── Standard Moodle fields ────────────────────────────────────────────
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

        // ── JS: reload assignments when linked_course selection changes ───────
        $PAGE->requires->js_call_amd('mod_teachingpractice/mod_form', 'init');
    }

    /**
     * Repopulate the assignment dropdown after form data is loaded.
     *
     * Assignments are loaded via JavaScript on the client, but on submit Moodle
     * rebuilds the form server-side. Select elements only accept values that exist
     * in their options list — so we must reload assignments here or the saved
     * linked_assign value is discarded and validation fails.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        global $DB;
        $mform = $this->_form;

        $linkedcourse = $mform->getElementValue('linked_course');
        if (is_array($linkedcourse)) {
            $linkedcourse = reset($linkedcourse);
        }
        $linkedcourse = (int) $linkedcourse;

        if ($linkedcourse <= 0) {
            return;
        }

        $assignments = $DB->get_records(
            'assign',
            ['course' => $linkedcourse],
            'name ASC',
            'id, name'
        );

        $options = ['' => '--- Select assignment ---'];
        foreach ($assignments as $a) {
            $options[$a->id] = $a->name;
        }

        $mform->getElement('linked_assign')->load($options);
    }

    /**
     * Server-side validation.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['linked_course'])) {
            $errors['linked_course'] = 'Please select the linked project submission course.';
        }

        if (empty($data['linked_assign'])) {
            $errors['linked_assign'] = 'Please select the project submission assignment.';
        }

        // Role validation is now handled at global configuration level.

        return $errors;
    }
}
