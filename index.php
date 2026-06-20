<?php
/**
 * index.php
 *
 * Required by Moodle for every activity module.
 * Lists all Teaching Practice activity instances within a course.
 *
 * URL: /mod/teachingpractice/index.php?id=COURSE_ID
 */

require_once('../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$PAGE->set_url(new moodle_url('/mod/teachingpractice/index.php', ['id' => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_course($course);
$PAGE->set_title($course->fullname . ' — Teaching Practice Activities');
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading('Teaching Practice Activities', 2);

// Fetch all instances in this course
$instances = $DB->get_records('teachingpractice', ['course' => $courseid], 'name ASC');

if (empty($instances)) {
    echo $OUTPUT->notification('No Teaching Practice activities found in this course.', 'info');
    echo $OUTPUT->footer();
    exit;
}

$table             = new html_table();
$table->head       = ['Activity Name', 'Linked Course', 'Open'];
$table->attributes = ['class' => 'table table-bordered generaltable'];

foreach ($instances as $inst) {
    // Get the course module ID for this instance
    $cm = get_coursemodule_from_instance('teachingpractice', $inst->id, $courseid);

    // Get linked course name
    $linked = $DB->get_record('course', ['id' => $inst->linked_course], 'id, fullname, shortname');
    $linked_name = $linked
        ? $linked->fullname . ' (' . $linked->shortname . ')'
        : 'Not configured';

    $view_url = new moodle_url('/mod/teachingpractice/view.php', ['id' => $cm->id]);

    $table->data[] = [
        html_writer::link($view_url, $inst->name),
        $linked_name,
        html_writer::link($view_url, 'Open', ['class' => 'btn btn-sm btn-primary']),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
