<?php
/**
 * search_courses.php
 *
 * External (AJAX) function that returns a list of courses matching a search query.
 * Used by the autocomplete element in mod_form.php to search across 1500+ courses.
 *
 * Called by: amd/src/course_selector.js
 */
namespace mod_teachingpractice\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class search_courses extends external_api {

    /**
     * Define the input parameters for this function.
     */
    public static function search_courses_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search string typed by the user'),
        ]);
    }

    /**
     * Search for courses by fullname or shortname.
     * Returns up to 30 results sorted by fullname.
     *
     * @param string $query Search string
     * @return array List of matching courses [{value, label}]
     */
    public static function search_courses($query) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(
            self::search_courses_parameters(),
            ['query' => $query]
        );

        // Must be logged in
        require_login();

        $query   = trim($params['query']);
        $results = [];

        if (strlen($query) < 2) {
            return $results;
        }

        // Search in fullname and shortname — exclude site course (id=1)
        $sql = "SELECT id, fullname, shortname
                  FROM {course}
                 WHERE id > 1
                   AND (
                         " . $DB->sql_like('fullname',  ':q1', false) . "
                      OR " . $DB->sql_like('shortname', ':q2', false) . "
                   )
              ORDER BY fullname ASC
                 LIMIT 30";

        $like    = '%' . $DB->sql_like_escape($query) . '%';
        $courses = $DB->get_records_sql($sql, ['q1' => $like, 'q2' => $like]);

        foreach ($courses as $course) {
            $results[] = [
                'value' => (int) $course->id,
                'label' => $course->fullname . ' (' . $course->shortname . ')',
            ];
        }

        return $results;
    }

    /**
     * Define the return structure.
     */
    public static function search_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'value' => new external_value(PARAM_INT,  'Course ID'),
                'label' => new external_value(PARAM_TEXT, 'Course fullname (shortname)'),
            ])
        );
    }
}
