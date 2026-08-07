// amd/src/mod_form.js
//
// Watches the linked_course autocomplete field.
// When a course is selected, fetches that course's assignments via AJAX
// and repopulates the linked_assign dropdown automatically.

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    /**
     * Fetch assignments for a course and populate the select.
     *
     * @param {HTMLSelectElement} assignSelect
     * @param {string|number} courseId
     * @param {string|number} preselectId
     */
    function loadAssignments(assignSelect, courseId, preselectId) {
        if (!courseId || courseId === '0') {
            assignSelect.innerHTML =
                '<option value="">--- Select linked course first ---</option>';
            assignSelect.disabled = false;
            return;
        }

        assignSelect.innerHTML =
            '<option value="">Loading assignments...</option>';
        assignSelect.disabled = true;

        Ajax.call([{
            methodname: 'mod_researchproject_get_assignments',
            args: {courseid: parseInt(courseId, 10)},
            done: function(assignments) {
                assignSelect.disabled = false;
                assignSelect.innerHTML = '';

                if (!assignments || assignments.length === 0) {
                    assignSelect.innerHTML =
                        '<option value="">No assignments found in this course</option>';
                    return;
                }

                var def = document.createElement('option');
                def.value = '';
                def.text = '--- Select assignment ---';
                assignSelect.appendChild(def);

                assignments.forEach(function(a) {
                    var opt = document.createElement('option');
                    opt.value = a.id;
                    opt.text = a.name;
                    if (String(a.id) === String(preselectId)) {
                        opt.selected = true;
                    }
                    assignSelect.appendChild(opt);
                });
            },
            fail: function(err) {
                assignSelect.disabled = false;
                assignSelect.innerHTML =
                    '<option value="">Error loading assignments</option>';
                Notification.exception(err);
            }
        }]);
    }

    return {
        init: function() {
            // Autocomplete renders as a hidden <select id="id_linked_course">.
            var courseSelect = document.getElementById('id_linked_course');
            var assignSelect = document.getElementById('id_linked_assign');

            if (!courseSelect || !assignSelect) {
                return;
            }

            var savedAssignId = assignSelect.value || '';

            // core/form-autocomplete dispatches a native change event on the select.
            $(courseSelect).on('change', function() {
                loadAssignments(assignSelect, this.value, '');
            });

            // Edit mode: course is already selected — load its assignments.
            if (courseSelect.value && courseSelect.value !== '0') {
                loadAssignments(assignSelect, courseSelect.value, savedAssignId);
            }
        }
    };
});
