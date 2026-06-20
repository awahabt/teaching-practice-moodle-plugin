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
        $role     = $this->_customdata['role'];
        $performa = $this->_customdata['performa'];
        $student  = $this->_customdata['student'];
        $cmid     = $this->_customdata['cmid'];

        $rating_opts = tp_get_rating_options();
        $rec_opts    = tp_get_recommendation_options();

        // Hidden fields
        $mform->addElement('hidden', 'id',        $cmid);
        $mform->addElement('hidden', 'studentid', $student->id);
        $mform->addElement('hidden', 'role',      $role);
        $mform->setType('id',        PARAM_INT);
        $mform->setType('studentid', PARAM_INT);
        $mform->setType('role',      PARAM_ALPHA);

        // =====================================================================
        // SECTION A: Basic Teaching Practice Information
        // Filled by CT first. Shown as read-only to HT.
        // =====================================================================
        $mform->addElement('header', 'section_a', 'Section A: Teaching Practice Information');
        $mform->setExpanded('section_a', true);

        // Student name — display only
        $mform->addElement('static', 'student_name_display', 'Trainee Teacher Name',
            html_writer::tag('strong', fullname($student)));

        $mform->addElement('text', 'registration_no', 'Registration No.',
            ['size' => 20, 'maxlength' => 50]);
        $mform->setType('registration_no', PARAM_TEXT);
        $mform->addRule('registration_no', 'Registration number is required.', 'required', null, 'client');

        $mform->addElement('text', 'school_name', 'School Name',
            ['size' => 60, 'maxlength' => 255]);
        $mform->setType('school_name', PARAM_TEXT);
        $mform->addRule('school_name', 'School name is required.', 'required', null, 'client');

        $mform->addElement('date_selector', 'start_date', 'Teaching Practice Start Date');
        $mform->addElement('date_selector', 'end_date',   'Teaching Practice End Date');

        $mform->addElement('text', 'morning_time', 'Morning Time (Arrival)',
            ['size' => 15, 'maxlength' => 20, 'placeholder' => 'e.g. 08:00 AM']);
        $mform->setType('morning_time', PARAM_TEXT);
        $mform->addRule('morning_time', 'Morning time is required.', 'required', null, 'client');

        $mform->addElement('text', 'afternoon_time', 'Afternoon Time (Departure)',
            ['size' => 15, 'maxlength' => 20, 'placeholder' => 'e.g. 01:30 PM']);
        $mform->setType('afternoon_time', PARAM_TEXT);
        $mform->addRule('afternoon_time', 'Afternoon time is required.', 'required', null, 'client');

        $mform->addElement('text', 'days_count', 'Total Days of Teaching Practice',
            ['size' => 5, 'maxlength' => 5]);
        $mform->setType('days_count', PARAM_INT);
        $mform->addRule('days_count', 'Total days is required.', 'required', null, 'client');
        $mform->addRule('days_count', 'Must be a number.',        'numeric',  null, 'client');

        $mform->addElement('text', 'cooperating_teacher_name', 'Name of Cooperating Teacher',
            ['size' => 50, 'maxlength' => 255]);
        $mform->setType('cooperating_teacher_name', PARAM_TEXT);
        $mform->addRule('cooperating_teacher_name', 'Cooperating teacher name is required.', 'required', null, 'client');

        $mform->addElement('text', 'subject_1', 'Subject 1',
            ['size' => 40, 'maxlength' => 255]);
        $mform->setType('subject_1', PARAM_TEXT);
        $mform->addRule('subject_1', 'At least one subject is required.', 'required', null, 'client');

        $mform->addElement('text', 'subject_2', 'Subject 2 (optional)',
            ['size' => 40, 'maxlength' => 255]);
        $mform->setType('subject_2', PARAM_TEXT);

        $mform->addElement('text', 'subject_3', 'Subject 3 (optional)',
            ['size' => 40, 'maxlength' => 255]);
        $mform->setType('subject_3', PARAM_TEXT);

        // Freeze Section A for HT (CT already filled it)
        if ($role === 'ht' && $performa) {
            foreach (['registration_no','school_name','start_date','end_date',
                      'morning_time','afternoon_time','days_count',
                      'cooperating_teacher_name','subject_1','subject_2','subject_3'] as $f) {
                $mform->freeze($f);
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

        $this->add_action_buttons(true, 'Submit Evaluation');

        // Pre-fill fields with existing data if record exists
        if ($performa) {
            $this->set_data($performa);
        }
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if ($data['end_date'] < $data['start_date']) {
                $errors['end_date'] = 'End date cannot be earlier than start date.';
            }
        }
        if (isset($data['days_count']) && (int)$data['days_count'] <= 0) {
            $errors['days_count'] = 'Total days must be a positive number.';
        }
        return $errors;
    }
}
