<?php
/**
 * performa_form.php
 *
 * Moodle Quick Form for CT and HT evaluations.
 * All rating criteria use radio button groups.
 *
 * Customdata passed in:
 *   role      → 'ct' or 'ht'
 *   performa  → existing DB record or null
 *   studentid → student user ID
 *   cmid      → course module ID
 *   instance  → teachingpractice DB record
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/teachingpractice/lib.php');

class performa_form extends moodleform {

    public function definition() {
        global $DB, $USER;

        $mform    = $this->_form;
        $role        = $this->_customdata['role'];
        $performa    = $this->_customdata['performa'];
        $student     = $this->_customdata['student'];
        $cmid        = $this->_customdata['cmid'];
        $sectiondata = $this->_customdata['sectiondata'];
        $certno      = $this->_customdata['certno'] ?? null;

        $rating_opts = tp_get_rating_options();
        $rec_opts    = tp_get_recommendation_options();

        // Hidden fields — use cmid, not id (performa record also has an id field).
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->addElement('hidden', 'studentid', $student->id);
        $mform->addElement('hidden', 'role', $role);
        $mform->setType('cmid', PARAM_INT);
        $mform->setType('studentid', PARAM_INT);
        $mform->setType('role', PARAM_ALPHA);

        // =====================================================================
        // SECTION A: Teaching Practice Information (certificate preview)
        // Filled by the student. Shown read-only to CT and HT.
        // =====================================================================
        $mform->addElement('header', 'section_a',
            get_string('section_a_heading', 'mod_teachingpractice'));
        $mform->setExpanded('section_a', true);

        $mform->addElement('html', tp_render_section_a_certificate($student, $sectiondata, [
            'certno' => $certno,
        ]));

        foreach (tp_section_a_field_names() as $field) {
            $mform->addElement('hidden', $field, $sectiondata->$field ?? '');
            if ($field === 'days_count' || $field === 'start_date' || $field === 'end_date') {
                $mform->setType($field, PARAM_INT);
            } else {
                $mform->setType($field, PARAM_TEXT);
            }
        }

        // =====================================================================
        // SECTION B: Cooperating Teacher Evaluation (radio buttons)
        // Only shown when role = 'ct'
        // =====================================================================
        if ($role === 'ct') {

            $mform->addElement('header', 'section_b',
                'Section B: Evaluation by Cooperating Teacher');
            $mform->setExpanded('section_b', true);

            $mform->addElement('html',
                '<p class="text-muted mb-3">Please rate the trainee teacher on each criterion below.</p>');

            $ct_criteria = [
                'ct_subject_knowledge'      => '1. Subject Knowledge',
                'ct_lesson_planning'        => '2. Lesson Planning & Preparation',
                'ct_instructional_delivery' => '3. Instructional Delivery',
                'ct_classroom_management'   => '4. Classroom Management',
                'ct_assessment_feedback'    => '5. Assessment & Feedback',
                'ct_professionalism'        => '6. Professionalism & Communication',
            ];

            foreach ($ct_criteria as $field => $label) {
                $radios = [];
                foreach ($rating_opts as $val => $text) {
                    $radios[] = $mform->createElement('radio', $field, '', $text, $val);
                }
                $mform->addGroup($radios, $field . '_grp', $label, '&nbsp;&nbsp;&nbsp;', false);
                $mform->addRule($field . '_grp',
                    'Please select a rating for "' . $label . '".', 'required', null, 'client');
            }

            $mform->addElement('textarea', 'ct_remarks',
                'Additional Remarks (Optional)', ['rows' => 4, 'cols' => 60]);
            $mform->setType('ct_remarks', PARAM_TEXT);

            $mform->addElement('text', 'ct_teacher_name', 'Your Full Name (Cooperating Teacher)',
                ['size' => 50, 'maxlength' => 255]);
            $mform->setType('ct_teacher_name', PARAM_TEXT);
            $mform->addRule('ct_teacher_name', 'Your name is required.', 'required', null, 'client');
            $mform->setDefault('ct_teacher_name', fullname($USER));
        }

        // =====================================================================
        // SECTION C: Head Teacher Evaluation (radio buttons)
        // Only shown when role = 'ht'
        // =====================================================================
        if ($role === 'ht') {

            $mform->addElement('header', 'section_c',
                'Section C: Evaluation by Head Teacher');
            $mform->setExpanded('section_c', true);

            $mform->addElement('html',
                '<p class="text-muted mb-3">Please rate the trainee teacher on each criterion below.</p>');

            $ht_criteria = [
                'ht_attendance'                 => '1. Attendance and Regularity',
                'ht_punctuality'                => '2. Punctuality (Arrival and Departure)',
                'ht_participation_teaching'     => '3. Participation in Teaching–Learning Activities',
                'ht_participation_cocurricular' => '4. Participation in Co-curricular Activities',
                'ht_professional_conduct'       => '5. Professional Conduct and Collaboration',
            ];

            foreach ($ht_criteria as $field => $label) {
                $radios = [];
                foreach ($rating_opts as $val => $text) {
                    $radios[] = $mform->createElement('radio', $field, '', $text, $val);
                }
                $mform->addGroup($radios, $field . '_grp', $label, '&nbsp;&nbsp;&nbsp;', false);
                $mform->addRule($field . '_grp',
                    'Please select a rating for "' . $label . '".', 'required', null, 'client');
            }

            // Overall recommendation — also radio buttons
            $rec_radios = [];
            foreach ($rec_opts as $val => $text) {
                $rec_radios[] = $mform->createElement('radio', 'ht_overall_recommendation', '', $text, $val);
            }
            $mform->addGroup($rec_radios, 'ht_overall_recommendation_grp',
                'Overall Recommendation', html_writer::empty_tag('br'), false);
            $mform->addRule('ht_overall_recommendation_grp',
                'Please select an overall recommendation.', 'required', null, 'client');

            $mform->addElement('textarea', 'ht_remarks',
                'Additional Remarks (Optional)', ['rows' => 4, 'cols' => 60]);
            $mform->setType('ht_remarks', PARAM_TEXT);

            $mform->addElement('text', 'ht_teacher_name', 'Your Full Name (Head Teacher / Principal)',
                ['size' => 50, 'maxlength' => 255]);
            $mform->setType('ht_teacher_name', PARAM_TEXT);
            $mform->addRule('ht_teacher_name', 'Your name is required.', 'required', null, 'client');
            $mform->setDefault('ht_teacher_name', fullname($USER));
        }

        if ($performa) {
            $formdefaults = (array) $performa;
            unset($formdefaults['id']);
            $this->set_data($formdefaults);
        }

        $this->add_action_buttons(true, get_string('submit_evaluation', 'mod_teachingpractice'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
