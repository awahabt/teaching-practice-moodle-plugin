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
require_once($CFG->dirroot . '/mod/researchproject/lib.php');

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
        $mform->addElement('hidden', 'edit', !empty($this->_customdata['edit']) ? 1 : 0);
        $mform->setType('edit', PARAM_BOOL);
        $mform->setType('cmid', PARAM_INT);
        $mform->setType('studentid', PARAM_INT);
        $mform->setType('role', PARAM_ALPHA);

        // =====================================================================
        // SECTION A: Hidden fields (data carried through form submission)
        // The certificate preview has been removed from this form.
        // =====================================================================

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

            // Grades taught (Grade 1 - Grade 10), shown above subjects.
            $grades_options = tp_get_grade_options();
            $mform->addElement('autocomplete', 'ht_grades', 'Grades Taught (Select at least 3)', $grades_options, [
                'multiple' => true,
            ]);
            $mform->setType('ht_grades', PARAM_TEXT);

            // Subjects autocomplete field (searchable dropdown)
            $subjects_options = [
                'English' => 'English',
                'Urdu' => 'Urdu',
                'Mathematics' => 'Mathematics',
                'Islamiat' => 'Islamiat',
                'General Knowledge' => 'General Knowledge',
                'Social Studies' => 'Social Studies',
                'General Science' => 'General Science',
                'History' => 'History',
                'Geography' => 'Geography',
                'Pakistan Studies' => 'Pakistan Studies',
                'Art' => 'Art',
                'Artificial Intelligence & Robotics' => 'Artificial Intelligence & Robotics',
                'Biology' => 'Biology',
                'Chemistry' => 'Chemistry',
                'Computer Science' => 'Computer Science',
                'Computer Graphics & Animation' => 'Computer Graphics & Animation',
                'Economics' => 'Economics',
                'Education' => 'Education',
                'Food & Nutrition' => 'Food & Nutrition',
                'Health & Physical Education' => 'Health & Physical Education',
                'Home Economics' => 'Home Economics',
                'Physics' => 'Physics',
                'Clothing & Textile' => 'Clothing & Textile',
                'Islamic History' => 'Islamic History',
                'Islamic Studies' => 'Islamic Studies',
                'English Literature' => 'English Literature',
                'Regional Languages' => 'Regional Languages',
                'Arabic' => 'Arabic',
            ];

            // Saved per-grade subjects (used for defaults and to keep custom subjects selectable).
            $saved_grade_subjects = $performa ? tp_get_grade_subjects($performa) : [];
            foreach ($saved_grade_subjects as $gsubjects) {
                foreach ($gsubjects as $val) {
                    $subjects_options[$val] = $val;
                }
            }

            // One "Subjects Taught" picker per grade. Only the pickers for the
            // grades selected above are shown (toggled by the script below).
            $mform->addElement('html',
                '<p class="text-muted mt-3 mb-2" id="ht-grade-subjects-help">' .
                'For each grade selected above, choose the subject(s) taught in that grade. ' .
                'You may type a subject that is not in the list and press Enter.</p>');

            foreach ($grades_options as $grade) {
                $field = tp_grade_subject_field($grade);
                $mform->addElement('autocomplete', $field, 'Subjects Taught in ' . $grade, $subjects_options, [
                    'multiple' => true,
                    'tags' => true,
                ]);
                $mform->setType($field, PARAM_TEXT);
            }

            $grade_field_map = [];
            foreach ($grades_options as $grade) {
                $grade_field_map[$grade] = 'id_' . tp_grade_subject_field($grade);
            }

            $mform->addElement('html', '
                <div id="subject-spelling-warning" class="alert alert-warning mt-2 mb-2" style="display:none; padding: 8px 12px; font-size: 0.9rem;"></div>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    // Show the subject picker of each selected grade, hide the rest.
                    var gradeFields = ' . json_encode($grade_field_map) . ';
                    function toggleGradeSubjectFields() {
                        var gradeSelect = document.getElementById("id_ht_grades");
                        var selected = gradeSelect ? Array.from(gradeSelect.selectedOptions).map(function(o) { return o.value; }) : [];
                        Object.keys(gradeFields).forEach(function(grade) {
                            var el = document.getElementById(gradeFields[grade]);
                            var row = el ? (el.closest(".fitem") || el.closest(".form-group")) : null;
                            if (row) {
                                row.style.display = selected.indexOf(grade) !== -1 ? "" : "none";
                            }
                        });
                        var help = document.getElementById("ht-grade-subjects-help");
                        if (help) { help.style.display = selected.length ? "" : "none"; }
                    }
                    var gradeSelectEl = document.getElementById("id_ht_grades");
                    if (gradeSelectEl) { gradeSelectEl.addEventListener("change", toggleGradeSubjectFields); }
                    toggleGradeSubjectFields();
                    setInterval(toggleGradeSubjectFields, 700);
                });
                </script>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const dictionary = ["Accounting",  "Agriculture",  "Anatomy",  "Arabic",  "Art",  "Artificial Intelligence",  "Astronomy",  "Biochemistry",  "Biology",  "Botany",  "Business Administration",  "Business Studies",  "Calculus",  "Chemistry",  "Civics",  "Commerce",  "Communication Skills",  "Computer Applications",  "Computer Science",  "Creative Writing",  "Data Science",  "Design",  "Digital Literacy",  "Economics",  "Education",  "Electrical Engineering",  "Electronics",  "English",  "Environmental Science",  "Ethics",  "Finance",  "Fine Arts",  "Food Science",  "French",  "General Knowledge",  "Geography",  "Geology",  "Graphic Design",  "Health Education",  "Hindi",  "History",  "Home Economics",  "Human Resource Management",  "Information Technology",  "International Relations",  "Islamic Studies",  "Journalism",  "Law",  "Library Science",  "Linguistics",  "Literature",  "Management",  "Marketing",  "Mathematics",  "Mechanical Engineering",  "Media Studies",  "Microbiology",  "Music",  "Natural Science",  "Nursing",  "Pak Studies",  "Persian",  "Philosophy",  "Physical Education",  "Physics",  "Political Science",  "Programming",  "Psychology",  "Public Administration",  "Public Speaking",  "Punjabi",  "Research Methodology",  "Robotics",  "Science",  "Sindhi",  "Social Studies",  "Sociology",  "Software Engineering",  "Spanish",  "Statistics",  "Urdu",  "Veterinary Science",  "Web Development",  "World History",  "Zoology"];

                    function levenshtein(a, b) {
                        var tmp;
                        if (a.length === 0) { return b.length; }
                        if (b.length === 0) { return a.length; }
                        if (a.length > b.length) { tmp = a; a = b; b = tmp; }

                        var row = Array(a.length + 1);
                        for (var i = 0; i <= a.length; i++) { row[i] = i; }

                        for (var i = 1; i <= b.length; i++) {
                            var prev = i;
                            for (var j = 1; j <= a.length; j++) {
                                var val;
                                if (b[i - 1].toLowerCase() === a[j - 1].toLowerCase()) {
                                    val = row[j - 1];
                                } else {
                                    val = Math.min(row[j - 1] + 1, prev + 1, row[j] + 1);
                                }
                                row[j - 1] = prev;
                                prev = val;
                            }
                            row[a.length] = prev;
                        }
                        return row[a.length];
                    }

                    function checkWord(word) {
                        if (!word || word.length < 3) return null;
                        word = word.trim();
                        var exact = dictionary.find(s => s.toLowerCase() === word.toLowerCase());
                        if (exact) return null;

                        var bestMatch = null;
                        var minDistance = 999;
                        for (var i = 0; i < dictionary.length; i++) {
                            var dist = levenshtein(word, dictionary[i]);
                            if (dist < minDistance) {
                                minDistance = dist;
                                bestMatch = dictionary[i];
                            }
                        }
                        if (minDistance > 0 && minDistance <= 2) {
                            return bestMatch;
                        }
                        return null;
                    }

                    var warningDiv = document.getElementById("subject-spelling-warning");

                    function subjectSelects() {
                        return Array.from(document.querySelectorAll("select[id^=\'id_ht_gsub_\']"));
                    }

                    function updateSpellingWarning() {
                        var warnings = [];
                        // Text currently being typed in any per-grade subject box.
                        document.querySelectorAll("input[id^=\'id_ht_gsub_\'][id$=\'-input\'], .fitem input[type=\'text\'][id*=\'ht_gsub_\']").forEach(function(inputField) {
                            var typedVal = inputField.value.trim();
                            var suggestion = checkWord(typedVal);
                            if (suggestion) {
                                warnings.push("Did you mean \"<strong>" + suggestion + "</strong>\" instead of \"" + typedVal + "\"?");
                            }
                        });
                        // Already-selected subjects in every visible grade.
                        subjectSelects().forEach(function(select) {
                            var row = select.closest(".fitem") || select.closest(".form-group");
                            if (row && row.style.display === "none") { return; }
                            Array.from(select.selectedOptions).forEach(function(o) {
                                var val = o.value;
                                var suggestion = checkWord(val);
                                if (suggestion) {
                                    warnings.push("Selected subject \"" + val + "\" might be misspelled. Did you mean \"<strong>" + suggestion + "</strong>\"?");
                                }
                            });
                        });
                        warnings = warnings.filter(function(w, i) { return warnings.indexOf(w) === i; });
                        if (warnings.length > 0) {
                            warningDiv.innerHTML = "⚠️ " + warnings.join("<br>⚠️ ");
                            warningDiv.style.display = "block";
                        } else {
                            warningDiv.innerHTML = "";
                            warningDiv.style.display = "none";
                        }
                    }

                    subjectSelects().forEach(function(selectEl) {
                        selectEl.addEventListener("change", updateSpellingWarning);
                    });
                    setInterval(updateSpellingWarning, 1000);
                });
                </script>
            ');

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

            // Overall recommendation — single check mark.
            // Ticked → 'completed', unticked → '' (rejected in validation()).
            $mform->addElement('advcheckbox', 'ht_overall_recommendation', 'Overall Recommendation',
                $rec_opts['completed'], null, ['', 'completed']);
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
            // Drop empty values so cleared fields don't override setDefault()
            // (e.g. the teacher name defaulting to the current user).
            $formdefaults = array_filter((array) $performa, function($v) {
                return $v !== null && $v !== '';
            });
            unset($formdefaults['id']);

            if ($role === 'ht') {
                if (!empty($performa->start_date)) {
                    $formdefaults['ht_start_date'] = $performa->start_date;
                }
                if (!empty($performa->end_date)) {
                    $formdefaults['ht_end_date'] = $performa->end_date;
                }
                $selected_grades = json_decode($performa->grades_taught ?? '', true);
                $formdefaults['ht_grades'] = is_array($selected_grades) ? $selected_grades : [];

                foreach ($saved_grade_subjects as $grade => $gsubjects) {
                    $formdefaults[tp_grade_subject_field($grade)] = $gsubjects;
                }
            }

            $this->set_data($formdefaults);
        }

        $this->add_action_buttons(true, get_string('submit_evaluation', 'mod_researchproject'));
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

            // Check grades (must select at least 3)
            if (empty($data['ht_grades']) || !is_array($data['ht_grades']) || count($data['ht_grades']) < 3) {
                $errors['ht_grades'] = 'Please select at least 3 grades.';
            }

            // Overall recommendation check mark must be ticked.
            if (empty($data['ht_overall_recommendation']) || $data['ht_overall_recommendation'] !== 'completed') {
                $errors['ht_overall_recommendation'] = 'Please tick "' .
                    get_string('rec_completed', 'mod_researchproject') . '" to confirm the overall recommendation.';
            }

            // Every selected grade must have at least one subject.
            $all_subjects = [];
            $grades = (!empty($data['ht_grades']) && is_array($data['ht_grades'])) ? $data['ht_grades'] : [];
            foreach ($grades as $grade) {
                $field = tp_grade_subject_field($grade);
                $gsubjects = (!empty($data[$field]) && is_array($data[$field])) ? $data[$field] : [];
                if (empty($gsubjects)) {
                    $errors[$field] = 'Please select at least one subject taught in ' . $grade . '.';
                } else if (in_array('Other', $gsubjects)) {
                    $errors[$field] = 'Please type your custom subject and press Enter, or remove "Other" from the list.';
                }
                $all_subjects = array_merge($all_subjects, $gsubjects);
            }

            // Overall, at least 3 different subjects must be taught.
            $all_subjects = array_unique(array_map('core_text::strtolower', $all_subjects));
            if ($grades && count($all_subjects) < 3 && empty($errors['ht_grades'])) {
                $errors['ht_grades'] = 'Please select at least 3 different subjects across the selected grades.';
            }
        }

        return $errors;
    }
}
