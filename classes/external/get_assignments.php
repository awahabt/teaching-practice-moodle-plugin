<?php
/**
 * get_assignments.php
 *
 * External (AJAX) function that returns the assignments of a given course.
 * Called when teacher selects a course in mod_form.php — the assignment
 * dropdown is then populated dynamically.
 */
namespace mod_teachingpractice\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class get_assignments extends external_api {

    public static function get_assignments_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID to fetch assignments from'),
        ]);
    }

    /**
     * Fetch all assignments in a given course.
     *
     * @param int $courseid
     * @return array [{id, name}]
     */
    public static function get_assignments($courseid) {
        global $DB;

        $params = self::validate_parameters(
            self::get_assignments_parameters(),
            ['courseid' => $courseid]
        );

        require_login();

        if (!$DB->record_exists('course', ['id' => $params['courseid']])) {
            throw new \invalid_parameter_exception('Invalid course ID');
        }

        $assigns = $DB->get_records(
            'assign',
            ['course' => $params['courseid']],
            'name ASC',
            'id, name'
        );

        $result = [];
        foreach ($assigns as $a) {
            $result[] = [
                'id'   => (int) $a->id,
                'name' => $a->name,
            ];
        }

        return $result;
    }

    public static function get_assignments_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id'   => new external_value(PARAM_INT,  'Assignment ID'),
                'name' => new external_value(PARAM_TEXT, 'Assignment name'),
            ])
        );
    }
}
