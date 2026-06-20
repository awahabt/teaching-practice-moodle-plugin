<?php
defined('MOODLE_INTERNAL') || die();

// Plugin name — shown in "Add an activity" list
$string['pluginname']               = 'Teaching Practice';
$string['modulename']               = 'Teaching Practice';
$string['modulenameplural']         = 'Teaching Practices';
$string['modulename_help']          = 'The Teaching Practice activity allows students to receive their teaching practice completion certificate after evaluation by a Cooperating Teacher and Head Teacher.';
$string['pluginadministration']    = 'Teaching Practice administration';

// Capabilities
$string['teachingpractice:addinstance']     = 'Add a new Teaching Practice activity';
$string['teachingpractice:view']            = 'View Teaching Practice activity';
$string['teachingpractice:fillct']          = 'Submit Cooperating Teacher evaluation';
$string['teachingpractice:fillht']          = 'Submit Head Teacher evaluation';
$string['teachingpractice:viewcertificate'] = 'View Teaching Practice certificate';

// Activity settings form labels (mod_form.php)
$string['linked_course']           = 'Linked Course (Project Submission Course)';
$string['linked_course_help']      = 'Select the course where students submit their teaching practice project. Search by course name or short name.';
$string['linked_assign']           = 'Project Submission Assignment';
$string['linked_assign_help']      = 'Select the assignment in the linked course that counts as project submission. Save the linked course first to see its assignments.';
$string['student_role']            = 'Student Role';
$string['student_role_help']       = 'Select the Moodle role assigned to students in this course.';
$string['ct_role']                 = 'Cooperating Teacher Role';
$string['ct_role_help']            = 'Select the Moodle role assigned to Cooperating Teachers in this course.';
$string['ht_role']                 = 'Head Teacher Role';
$string['ht_role_help']            = 'Select the Moodle role assigned to Head Teachers in this course.';
$string['roles_heading']           = 'Role Configuration';
$string['course_heading']          = 'Linked Course Configuration';

// Performa form strings
$string['registration_no']          = 'Registration No.';
$string['school_name']              = 'School Name';
$string['start_date']               = 'Teaching Practice Start Date';
$string['end_date']                 = 'Teaching Practice End Date';
$string['morning_time']             = 'Morning Time (Arrival)';
$string['afternoon_time']           = 'Afternoon Time (Departure)';
$string['days_count']               = 'Total Days of Teaching Practice';
$string['cooperating_teacher_name'] = 'Name of Cooperating Teacher';
$string['subject_1']                = 'Subject 1';
$string['subject_2']                = 'Subject 2 (optional)';
$string['subject_3']                = 'Subject 3 (optional)';
$string['trainee_teacher_name']     = 'Trainee Teacher Name';
$string['section_a_heading']        = 'Section A: Teaching Practice Information';
$string['submit_evaluation']        = 'Submit Evaluation';
$string['certificate_title']        = 'Teaching Practice Completion Certificate';
$string['certno_label']             = 'Certificate No.:';
$string['certno_pending']           = 'To be issued';
$string['certificate_subjects_intro']= 'During the teaching practice, the trainee teacher taught the following subject(s):';

// Rating labels
$string['rating_excellent']         = 'Excellent';
$string['rating_verygood']          = 'Very Good';
$string['rating_good']              = 'Good';
$string['rating_fair']              = 'Fair';
$string['rating_needsimprovement']  = 'Needs Improvement';

// Recommendation labels
$string['rec_completed']            = 'Successfully Completed Teaching Practice';
$string['rec_completed_minor']      = 'Successfully Completed with Minor Recommendations';
$string['rec_further_improvement']  = 'Further Improvement Recommended';

// Status messages shown on screen
$string['status_pending']           = 'Pending';
$string['status_ct_done']           = 'CT Evaluation Submitted';
$string['status_completed']         = 'Certificate Issued';

// Notifications
$string['msg_project_not_submitted']   = 'Your Teaching Practice certificate will be available here once you have submitted your project in the linked course and both evaluations have been completed.';
$string['msg_evaluation_inprogress']   = 'Your project has been submitted. Your certificate will appear here once both teachers complete their evaluations.';
$string['msg_ct_already_submitted']    = 'You have already submitted the Cooperating Teacher evaluation for this student.';
$string['msg_ht_wait_for_ct']          = 'Head Teacher evaluation cannot be submitted until the Cooperating Teacher has completed their evaluation first.';
$string['msg_already_completed']       = 'Evaluation already completed. The certificate has been issued.';
$string['msg_performa_saved']          = 'Cooperating Teacher evaluation submitted successfully.';
$string['msg_certificate_issued']      = 'Head Teacher evaluation submitted. Certificate has been issued.';
$string['msg_not_configured']          = 'This activity has not been fully configured yet. Please contact your administrator.';
$string['msg_no_students']             = 'No students are currently enrolled in this course.';
$string['msg_project_not_submitted_ct']= 'This student has not yet submitted their project. Evaluation cannot proceed.';
$string['msg_section_a_autofetch_help'] = 'Teaching practice details are loaded automatically from the student\'s Moodle profile and linked assignment submission. Ensure the student profile (ID number, institution, custom profile fields) or assignment online text contains the required information.';

// Error strings
$string['error_no_performa']        = 'No evaluation record found for this student.';
$string['error_not_complete']       = 'Certificate is not available yet. Both evaluations must be completed first.';
$string['error_no_certificate']     = 'Certificate record not found. Please contact the administrator.';
$string['error_invalid_role']       = 'You do not have an assigned role in this Teaching Practice activity. Please contact your administrator.';
$string['error_end_before_start']   = 'End date cannot be earlier than start date.';
$string['error_days_positive']      = 'Total days must be a positive number.';
