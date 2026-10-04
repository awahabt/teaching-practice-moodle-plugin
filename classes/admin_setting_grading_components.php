<?php
/**
 * admin_setting_grading_components.php
 *
 * A multi-select admin setting enhanced with Moodle's core/form-autocomplete
 * JS module — the same searchable, chip-style widget used by the "Subjects
 * Taught" field on the Head Teacher evaluation form
 * (classes/form/performa_form.php, element 'ht_subjects').
 *
 * Unlike that field, "tags" entry is disabled here: the options are the real
 * assignment names found in the linked project-submission courses, so free-
 * typed values that don't match an actual assignment would never filter
 * anything. Admins can only pick from the list, search to narrow it, and
 * select one (checks that component only) or several (averages across them).
 *
 * Deliberately NOT namespaced — same as classes/form/performa_form.php in
 * this plugin, it is loaded via an explicit require_once() (from
 * settings.php) rather than relying on Moodle's component class autoloader,
 * which only recognises classes/ files on namespaces it caches.
 */
defined('MOODLE_INTERNAL') || die();

class mod_researchproject_admin_setting_grading_components extends admin_setting_configmultiselect {

    public function output_html($data, $query = '') {
        global $PAGE;

        $html = parent::output_html($data, $query);

        // The JS module takes a jQuery-style CSS selector (needs the '#'),
        // not a bare element id — this is the part the earlier attempt missed.
        $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
            '#' . $this->get_id(),
            false, // tags — restrict to the known assignment-name list only.
            false, // ajax — options are preloaded, no server-side search needed.
            get_string('grading_components_placeholder', 'mod_researchproject'),
        ]);

        return $html;
    }
}
