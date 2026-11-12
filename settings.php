<?php
/**
 * settings.php
 *
 * Site-wide administration settings for mod_researchproject.
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/researchproject/lib.php');
require_once($CFG->dirroot . '/mod/researchproject/classes/admin_setting_grading_components.php');

if ($ADMIN->fulltree) {
    // Collapsible sections: every heading on THIS settings page becomes a
    // click-to-expand panel (script checks the URL, so other admin pages and
    // the admin search page are untouched). Open/closed state is remembered
    // per browser; a section holding a validation error always opens.
    if (!CLI_SCRIPT && !during_initial_install() && isset($PAGE)
            && $PAGE->state === moodle_page::STATE_BEFORE_HEADER) {
        $PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var init = function() {
        if (new URLSearchParams(window.location.search).get('section') !== 'modsettingresearchproject') {
            return;
        }
        var headings = document.querySelectorAll('#adminsettings h3.main');
        if (!headings.length) {
            return;
        }
        var css = document.createElement('style');
        css.textContent =
            '.tp-sec-head{display:flex;align-items:center;gap:10px;cursor:pointer;user-select:none;margin:18px 0 0;' +
            'padding:12px 16px;background:#f6f8fa;border:1px solid #dfe3e8;border-radius:8px;font-size:1.15rem}' +
            '.tp-sec-head:hover{background:#eef2f6}' +
            '.tp-sec-head .tp-sec-chev{transition:transform .2s;display:inline-block;width:1em;text-align:center}' +
            '.tp-sec-head.is-open{border-radius:8px 8px 0 0}' +
            '.tp-sec-head.is-open .tp-sec-chev{transform:rotate(90deg)}' +
            '.tp-sec-head .tp-sec-count{margin-left:auto;font-size:.8rem;font-weight:400;color:#6a737d}' +
            '.tp-sec-body{border:1px solid #dfe3e8;border-top:0;border-radius:0 0 8px 8px;padding:12px 16px 4px}' +
            '.tp-sec-body[hidden]{display:none!important}' +
            '.tp-sec-tools{margin:8px 0 0;text-align:right}';
        document.head.appendChild(css);

        var store = {
            get: function(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
            set: function(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* ignore */ } }
        };
        var sections = [];

        headings.forEach(function(h, i) {
            // Move everything up to the next heading into a body wrapper.
            var body = document.createElement('div');
            body.className = 'tp-sec-body';
            body.id = 'tp-sec-body-' + i;
            var node = h.nextElementSibling;
            var count = 0;
            while (node && !(node.matches('h3.main'))) {
                var next = node.nextElementSibling;
                if (node.classList.contains('form-item')) {
                    count++;
                }
                body.appendChild(node);
                node = next;
            }
            h.parentNode.insertBefore(body, h.nextSibling);

            var title = h.textContent.trim();
            h.classList.add('tp-sec-head');
            h.setAttribute('role', 'button');
            h.setAttribute('tabindex', '0');
            h.setAttribute('aria-controls', body.id);
            h.innerHTML = '<span class="tp-sec-chev" aria-hidden="true">&#9656;</span>';
            h.appendChild(document.createTextNode(title));
            var badge = document.createElement('span');
            badge.className = 'tp-sec-count';
            badge.textContent = count + (count === 1 ? ' setting' : ' settings');
            h.appendChild(badge);

            var key = 'tp-settings-open:' + title;
            var hasError = !!body.querySelector('.error, .invalid-feedback, .alert-danger, .form-control.is-invalid');
            var setOpen = function(open, remember) {
                body.hidden = !open;
                h.classList.toggle('is-open', open);
                h.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (remember) {
                    store.set(key, open ? '1' : '0');
                }
            };
            setOpen(hasError || store.get(key) === '1', false);

            var toggle = function() { setOpen(body.hidden, true); };
            h.addEventListener('click', toggle);
            h.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
            sections.push(setOpen);
        });

        // Expand all / Collapse all.
        var tools = document.createElement('div');
        tools.className = 'tp-sec-tools';
        [['Expand all', true], ['Collapse all', false]].forEach(function(cfg) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn btn-sm btn-link';
            b.textContent = cfg[0];
            b.addEventListener('click', function() {
                sections.forEach(function(setOpen) { setOpen(cfg[1], true); });
            });
            tools.appendChild(b);
        });
        headings[0].parentNode.insertBefore(tools, headings[0]);
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
});
JS
        );
    }

    // Load all Moodle roles.
    $all_roles = role_get_names(null, ROLENAME_ORIGINAL);
    $role_options = array('' => '--- Select role ---');
    foreach ($all_roles as $role) {
        $role_options[$role->id] = $role->localname;
    }

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/student_role',
        get_string('student_role', 'mod_researchproject'),
        get_string('student_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/ct_role',
        get_string('ct_role', 'mod_researchproject'),
        get_string('ct_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    $settings->add(new admin_setting_configselect(
        'mod_researchproject/ht_role',
        get_string('ht_role', 'mod_researchproject'),
        get_string('ht_role_help', 'mod_researchproject'),
        '',
        $role_options
    ));

    // Signature configuration section.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/signature_heading_wrapper',
        get_string('signature_heading', 'mod_researchproject'),
        ''
    ));

    // Master switch: show / hide the signature block on certificates.
    $settings->add(new admin_setting_configcheckbox(
        'mod_researchproject/show_signature',
        get_string('show_signature', 'mod_researchproject'),
        get_string('show_signature_help', 'mod_researchproject'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/signature_name',
        get_string('signature_name', 'mod_researchproject'),
        get_string('signature_name_help', 'mod_researchproject'),
        'Prof. Dr. Ali Ahmed',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/signature_title',
        get_string('signature_title', 'mod_researchproject'),
        get_string('signature_title_help', 'mod_researchproject'),
        'Chairman / Department Head',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configstoredfile(
        'mod_researchproject/signature_image',
        get_string('signature_image', 'mod_researchproject'),
        get_string('signature_image_help', 'mod_researchproject'),
        'signature_image'
    ));

    // Course Grade / Passing Criteria section.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/passing_heading_wrapper',
        get_string('passing_heading', 'mod_researchproject'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_researchproject/passing_percentage',
        get_string('passing_percentage', 'mod_researchproject'),
        get_string('passing_percentage_help', 'mod_researchproject'),
        50,
        PARAM_INT
    ));

    // Master switch: when unticked, the certificate is no longer gated behind
    // project submission + a passing grade — it becomes available as soon as
    // both the CT and HT evaluations are complete. Ticked (default) preserves
    // the original behaviour.
    $settings->add(new admin_setting_configcheckbox(
        'mod_researchproject/require_submission_grading',
        get_string('require_submission_grading', 'mod_researchproject'),
        get_string('require_submission_grading_help', 'mod_researchproject'),
        1
    ));

    // Certificate management switch — let CT / HT update the certificates of
    // students they evaluated. Off by default.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/certificate_manage_heading_wrapper',
        get_string('certificate_manage_heading', 'mod_researchproject'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_researchproject/allow_certificate_update',
        get_string('allow_certificate_update', 'mod_researchproject'),
        get_string('allow_certificate_update_help', 'mod_researchproject'),
        0
    ));

    // ── Grading Components — one dropdown PER (course code + semester code) ──
    // group of project-submission (TP) courses. A course qualifies when its
    // shortname has the AIOU prefix, the same course code (2nd segment), the
    // TP role marker, the ODL mode, and a semester code — all AIOU-prefixed,
    // TP-marked courses that share BOTH the same course code AND the same
    // semester code are the same offering, so they share ONE dropdown here;
    // a different course code (or a different semester) always gets its own
    // separate dropdown. Searchable, chip-style multi-select — same widget as
    // the "Subjects Taught" field on the Head Teacher evaluation form. Pick
    // one component (checks that component only) or several (averages across
    // just those). Leave a group's field empty to use all of its assignments.
    $settings->add(new admin_setting_heading(
        'mod_researchproject/grading_components_heading_wrapper',
        get_string('grading_components_heading', 'mod_researchproject'),
        get_string('grading_components_heading_desc', 'mod_researchproject')
    ));

    $submission_groups = tp_get_submission_groups_with_components();

    if (empty($submission_groups)) {
        $settings->add(new admin_setting_description(
            'mod_researchproject/grading_components_empty',
            '',
            get_string('grading_components_none_found', 'mod_researchproject')
        ));
    } else {
        foreach ($submission_groups as $key => $group) {
            $settingname = tp_grading_components_setting_name($group['coursecode'], $group['semestercode']);
            $label = get_string('grading_components_group_label', 'mod_researchproject', (object) [
                'coursecode'   => $group['coursecode'],
                'semestercode' => $group['semestercode'],
            ]);

            $help = get_string('grading_components_help', 'mod_researchproject');
            if (empty($group['options'])) {
                $help .= ' ' . get_string('grading_components_group_empty', 'mod_researchproject');
            }

            $settings->add(new mod_researchproject_admin_setting_grading_components(
                'mod_researchproject/' . $settingname,
                get_string('grading_components_for_course', 'mod_researchproject', $label),
                $help,
                [],
                $group['options']
            ));
        }
    }
}
