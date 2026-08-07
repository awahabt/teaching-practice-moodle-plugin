// amd/src/course_selector.js
//
// AMD module used by the autocomplete element in mod_form.php.
// Moodle's autocomplete element calls the 'transport' function
// whenever the user types in the search box.
// We call our external AJAX function and return the results.

define(['core/ajax'], function(Ajax) {
    return {
        /**
         * Transport function called by Moodle's autocomplete element.
         *
         * @param {string} selector  CSS selector of the autocomplete input
         * @param {string} query     Text typed by the user
         * @param {function} callback  Call this with the results array
         */
        transport: function(selector, query, callback) {
            if (!query || query.length < 2) {
                callback([]);
                return;
            }

            Ajax.call([{
                methodname: 'mod_researchproject_search_courses',
                args: { query: query },
                done: function(results) {
                    // Results are [{value: courseId, label: "Course Name (SHORT)"}]
                    callback(results);
                },
                fail: function() {
                    callback([]);
                }
            }]);
        },

        /**
         * Process results before displaying them in the dropdown.
         * Here we just pass them through as-is.
         */
        processResults: function(selector, results) {
            return results;
        }
    };
});
