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
                $mform->setType($field, PARAM_ALPHA);
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

            // Start Date and End Date fields
            $mform->addElement('date_selector', 'ht_start_date', 'Start Date of Teaching Practice');
            $mform->addElement('date_selector', 'ht_end_date', 'End Date of Teaching Practice');

            // Subjects autocomplete field (searchable dropdown)
            $subjects_options = [
                'Math' => 'Math',
                'science' => 'Science',
                'englishh' => 'English',
                'urdu' => 'Urdu',
                'istamiyaat' => 'Islamiyaat',
                'history' => 'History',
            ];
            $mform->addElement('autocomplete', 'ht_subjects', 'Subjects Taught (Select at least 3)', $subjects_options, [
                'multiple' => true,
            ]);
            $mform->setType('ht_subjects', PARAM_TEXT);

            $mform->addElement('html',
                '<p class="text-muted mb-3 mt-4">Please rate the trainee teacher on each criterion below.</p>');

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
                $mform->setType($field, PARAM_ALPHA);
            }

            // Overall recommendation — select dropdown menu
            $rec_opts_select = ['' => 'Choose...'] + $rec_opts;
            $mform->addElement('select', 'ht_overall_recommendation', 'Overall Recommendation', $rec_opts_select);
            $mform->addRule('ht_overall_recommendation',
                'Please select an overall recommendation.', 'required', null, 'client');
            $mform->setType('ht_overall_recommendation', PARAM_ALPHANUMEXT);

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

            if ($role === 'ht') {
                if (!empty($performa->start_date)) {
                    $formdefaults['ht_start_date'] = $performa->start_date;
                }
                if (!empty($performa->end_date)) {
                    $formdefaults['ht_end_date'] = $performa->end_date;
                }
                $selected_subjects = [];
                if (!empty($performa->subject_1)) $selected_subjects[] = $performa->subject_1;
                if (!empty($performa->subject_2)) $selected_subjects[] = $performa->subject_2;
                if (!empty($performa->subject_3)) $selected_subjects[] = $performa->subject_3;
                $formdefaults['ht_subjects'] = $selected_subjects;
            }

            $this->set_data($formdefaults);
        }

        $this->add_action_buttons(true, get_string('submit_evaluation', 'mod_teachingpractice'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['role']) && $data['role'] === 'ht') {
            // Check start_date and end_date validity
            $start_date = 0;
            if (!empty($data['ht_start_date'])) {
                if (is_array($data['ht_start_date'])) {
                    $start_date = make_timestamp(
                        $data['ht_start_date']['year'],
                        $data['ht_start_date']['month'],
                        $data['ht_start_date']['day']
                    );
                } else {
                    $start_date = (int)$data['ht_start_date'];
                }
            }
            $end_date = 0;
            if (!empty($data['ht_end_date'])) {
                if (is_array($data['ht_end_date'])) {
                    $end_date = make_timestamp(
                        $data['ht_end_date']['year'],
                        $data['ht_end_date']['month'],
                        $data['ht_end_date']['day']
                    );
                } else {
                    $end_date = (int)$data['ht_end_date'];
                }
            }

            if ($start_date && $end_date && $end_date < $start_date) {
                $errors['ht_end_date'] = 'End date cannot be earlier than start date.';
            }

            // Check subjects (must select at least 3)
            if (empty($data['ht_subjects']) || !is_array($data['ht_subjects']) || count($data['ht_subjects']) < 3) {
                $errors['ht_subjects'] = 'Please select at least 3 subjects.';
            }
        }

        return $errors;
    }
}
