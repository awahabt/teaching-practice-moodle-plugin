// amd/src/mod_form.js
//
// Watches the linked_course autocomplete field.
// When a course is selected, fetches that course's assignments via AJAX
// and repopulates the linked_assign dropdown automatically.

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    return {
        init: function() {

            // Moodle autocomplete writes the selected value into a hidden
            // input whose id = 'id_linked_course'
            var courseInput  = document.getElementById('id_linked_course');
            var assignSelect = document.getElementById('id_linked_assign');

            if (!courseInput || !assignSelect) {
                return;
            }

            // Store the currently saved assignment ID so we can re-select
            // it after the assignment list reloads (edit mode)
            var savedAssignId = assignSelect.value || '';

            // Helper: fetch assignments for a course and populate the select
            function loadAssignments(courseId, preselectId) {
                if (!courseId || courseId === '0') {
                    assignSelect.innerHTML =
                        '<option value="">--- Select linked course first ---</option>';
                    return;
                }

                assignSelect.innerHTML =
                    '<option value="">Loading assignments...</option>';
                assignSelect.disabled = true;

                Ajax.call([{
                    methodname: 'mod_teachingpractice_get_assignments',
                    args: { courseid: parseInt(courseId, 10) },
                    done: function(assignments) {
                        assignSelect.disabled = false;
                        assignSelect.innerHTML = '';

                        if (!assignments || assignments.length === 0) {
                            assignSelect.innerHTML =
                                '<option value="">No assignments found in this course</option>';
                            return;
                        }

                        // Default empty option
                        var def    = document.createElement('option');
                        def.value  = '';
                        def.text   = '--- Select assignment ---';
                        assignSelect.appendChild(def);

                        assignments.forEach(function(a) {
                            var opt   = document.createElement('option');
                            opt.value = a.id;
                            opt.text  = a.name;
                            // Re-select previously saved assignment in edit mode
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

            // Moodle's autocomplete element updates the hidden input value
            // and fires a 'change' event when the user selects a course.
            courseInput.addEventListener('change', function() {
                loadAssignments(this.value, '');
            });

            // On page load in edit mode, the hidden input already has a value.
            // Trigger load so assignment list is populated with correct options.
            if (courseInput.value && courseInput.value !== '0') {
                loadAssignments(courseInput.value, savedAssignId);
            }
        }
    };
});
