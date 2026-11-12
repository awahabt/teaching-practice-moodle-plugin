<?php
/**
 * feedback.php
 *
 * TP system-improvement feedback. One form per role, exactly as in
 * "Reviewed TP Feedback Forms for TCT, HT and TT":
 *   Head Teacher (ht) · Cooperating Teacher (ct) · Trainee Teacher (student)
 * Form definitions live in tp_get_feedback_form() in lib.php.
 *
 * URL parameters:
 *   id       = course module ID
 *   action   = '' (fill own feedback) | 'report' (site admins)
 *   download = 'ht' | 'ct' | 'student' (with action=report: CSV export)
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/researchproject/lib.php');

$cmid     = required_param('id', PARAM_INT);
$action   = optional_param('action', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'researchproject');
$instance = $DB->get_record('researchproject', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if (!tp_feedback_available()) {
    // Plugin code is newer than the database; an admin must run the upgrade.
    throw new moodle_exception('feedback_not_ready', 'mod_researchproject',
        new moodle_url('/mod/researchproject/view.php', ['id' => $cmid]));
}

$pageurl = new moodle_url('/mod/researchproject/feedback.php', ['id' => $cmid] + ($action ? ['action' => $action] : []));
$viewurl = new moodle_url('/mod/researchproject/view.php', ['id' => $cmid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('feedback_title', 'mod_researchproject'));
$PAGE->set_heading($course->fullname);

$roles = ['ht' => 'Head Teacher', 'ct' => 'Cooperating Teacher', 'student' => 'Trainee Teacher'];

/**
 * Flatten one stored response into [column heading => value] for the report.
 */
function tp_feedback_report_columns(array $form, array $answers) {
    $cols = [];
    foreach ($form['info'] as $f) {
        if ($f['type'] === 'static') {
            continue;
        }
        $v = $answers['info'][$f['name']] ?? '';
        $cols[$f['label']] = is_array($v) ? implode(', ', $v) : (string) $v;
    }
    $scale = tp_feedback_rating_scale();
    $sno = 1;
    foreach ($form['sections'] as $section) {
        foreach ($section['items'] as $key => $label) {
            $r = $answers['ratings'][$key] ?? '';
            $cols[$sno . '. ' . $label] = $r !== '' ? $r . ' — ' . ($scale[$r] ?? '') : '';
            $sno++;
        }
    }
    $overall = tp_feedback_overall_options(true);
    $cols['Overall Satisfaction'] = $overall[$answers['overall'] ?? ''] ?? '';
    $cols['Suggestion for Improvement'] = $answers['suggestion'] ?? '';
    return $cols;
}

/**
 * Colour class for an average score out of 5.
 */
function tp_feedback_score_class($avg) {
    if ($avg >= 4) {
        return 'is-high';
    }
    if ($avg >= 3) {
        return 'is-good';
    }
    return $avg >= 2 ? 'is-mid' : 'is-low';
}

/**
 * Dashboard for one role: summary cards, satisfaction bar, per-statement
 * averages, suggestions and expandable individual responses.
 */
function tp_feedback_render_role_report(array $form, array $responses, moodle_url $csvurl) {
    $scale   = tp_feedback_rating_scale();
    $overall = tp_feedback_overall_options(true);
    $total   = count($responses);

    // ── Aggregate ────────────────────────────────────────────────────────────
    $itemstats = [];   // key => [sum, count, dist[5..1]]
    $allsum = 0;
    $allcount = 0;
    $overallcount = array_fill_keys(array_keys($overall), 0);
    $latest = 0;
    foreach ($responses as $resp) {
        $latest = max($latest, $resp['time']);
        foreach ($resp['answers']['ratings'] as $key => $value) {
            $value = (int) $value;
            if (!isset($scale[$value])) {
                continue;
            }
            $itemstats[$key] = $itemstats[$key] ?? ['sum' => 0, 'count' => 0, 'dist' => array_fill_keys(array_keys($scale), 0)];
            $itemstats[$key]['sum'] += $value;
            $itemstats[$key]['count']++;
            $itemstats[$key]['dist'][$value]++;
            $allsum += $value;
            $allcount++;
        }
        if (isset($overallcount[$resp['answers']['overall']])) {
            $overallcount[$resp['answers']['overall']]++;
        }
    }
    $allavg = $allcount ? $allsum / $allcount : 0;
    $satisfiedpct = $total ? round(100 * $overallcount['satisfied'] / $total) : 0;

    // ── Summary cards ────────────────────────────────────────────────────────
    $card = function($value, $label, $extra = '') {
        return html_writer::div(
            html_writer::div($value, 'tp-fbr-card-value ' . $extra) .
            html_writer::div($label, 'tp-fbr-card-label'),
            'tp-fbr-card'
        );
    };
    echo html_writer::div(
        $card($total, 'Responses') .
        $card(number_format($allavg, 2) . '<small> / 5</small>', 'Average rating', tp_feedback_score_class($allavg)) .
        $card($satisfiedpct . '%', 'Fully satisfied') .
        $card(userdate($latest, '%d %b %Y'), 'Latest response'),
        'tp-fbr-cards'
    );

    // ── Overall satisfaction (stacked bar) ───────────────────────────────────
    $segments = '';
    $legend   = '';
    $segclass = ['satisfied' => 'seg-sat', 'minor' => 'seg-minor', 'not_satisfied' => 'seg-not'];
    $current = tp_feedback_overall_options();
    foreach ($overall as $key => $label) {
        // Removed options only appear when old responses actually used them.
        if (!isset($current[$key]) && $overallcount[$key] === 0) {
            continue;
        }
        $pct = $total ? 100 * $overallcount[$key] / $total : 0;
        if ($pct > 0) {
            $segments .= html_writer::div($pct >= 8 ? round($pct) . '%' : '',
                'tp-fbr-seg ' . $segclass[$key], ['style' => 'width:' . $pct . '%', 'title' => $label . ': ' . $overallcount[$key]]);
        }
        $legend .= html_writer::span(
            html_writer::span('', 'tp-fbr-dot ' . $segclass[$key]) . s($label) . ' — <strong>' . $overallcount[$key] . '</strong>',
            'tp-fbr-legend-item'
        );
    }
    echo html_writer::div(
        html_writer::tag('h5', 'Overall Satisfaction') .
        html_writer::div($segments, 'tp-fbr-stack') .
        html_writer::div($legend, 'tp-fbr-legend'),
        'tp-fbr-panel'
    );

    // ── Per-statement averages ───────────────────────────────────────────────
    $html = html_writer::tag('h5', 'Ratings by Statement');
    $sno = 1;
    foreach ($form['sections'] as $section) {
        $html .= html_writer::div(s($section['title']), 'tp-fbr-section');
        foreach ($section['items'] as $key => $label) {
            $st  = $itemstats[$key] ?? ['sum' => 0, 'count' => 0, 'dist' => []];
            $avg = $st['count'] ? $st['sum'] / $st['count'] : 0;
            $dist = [];
            foreach ($scale as $v => $vl) {
                $dist[] = $vl . ': ' . ($st['dist'][$v] ?? 0);
            }
            $html .= html_writer::div(
                html_writer::div(html_writer::span($sno++ . '.', 'tp-fbr-sno') . s($label), 'tp-fbr-item-label') .
                html_writer::div(
                    html_writer::div(
                        html_writer::div('', 'tp-fbr-fill ' . tp_feedback_score_class($avg), ['style' => 'width:' . (20 * $avg) . '%']),
                        'tp-fbr-track', ['title' => implode(' · ', $dist)]
                    ) .
                    html_writer::span($st['count'] ? number_format($avg, 1) : '—', 'tp-fbr-score ' . tp_feedback_score_class($avg)),
                    'tp-fbr-item-bar'
                ),
                'tp-fbr-item'
            );
        }
    }
    echo html_writer::div($html, 'tp-fbr-panel');

    // ── Suggestions ──────────────────────────────────────────────────────────
    $quotes = '';
    foreach ($responses as $resp) {
        $text = trim((string) $resp['answers']['suggestion']);
        if ($text !== '') {
            $quotes .= html_writer::div(
                html_writer::div(nl2br(s($text)), 'tp-fbr-quote-text') .
                html_writer::div('— ' . s($resp['name']) . ', ' . userdate($resp['time'], '%d %b %Y'), 'tp-fbr-quote-by'),
                'tp-fbr-quote'
            );
        }
    }
    echo html_writer::div(
        html_writer::tag('h5', 'Suggestions for Improvement') .
        ($quotes !== '' ? html_writer::div($quotes, 'tp-fbr-quotes') : html_writer::div('No suggestions were written.', 'text-muted')),
        'tp-fbr-panel'
    );

    // ── Individual responses (click to expand) ───────────────────────────────
    $items = '';
    foreach ($responses as $resp) {
        $a = $resp['answers'];
        $info = '';
        foreach ($form['info'] as $f) {
            $v = $a['info'][$f['name']] ?? '';
            $v = is_array($v) ? implode(', ', $v) : (string) $v;
            $info .= html_writer::tag('dt', s($f['label'])) . html_writer::tag('dd', $v !== '' ? s($v) : '—');
        }
        $chips = '';
        $sno = 1;
        foreach ($form['sections'] as $section) {
            foreach ($section['items'] as $key => $label) {
                $r = (int) ($a['ratings'][$key] ?? 0);
                $chips .= html_writer::span($sno++ . ': ' . ($r ?: '—'), 'tp-fbr-chip ' . ($r ? tp_feedback_score_class($r) : ''),
                    ['title' => $label . ($r ? ' — ' . $scale[$r] : '')]);
            }
        }
        $summary = html_writer::span(s($resp['name']), 'font-weight-bold fw-bold') .
            html_writer::span(userdate($resp['time'], '%d %b %Y, %H:%M'), 'text-muted small ml-2 ms-2') .
            html_writer::span(s($overall[$a['overall']] ?? '—'),
                'tp-fbr-pill ' . ($segclass[$a['overall']] ?? ''));
        $items .= html_writer::tag('details',
            html_writer::tag('summary', $summary) .
            html_writer::div(
                html_writer::tag('dl', $info, ['class' => 'tp-fbr-dl']) .
                html_writer::div($chips, 'tp-fbr-chips') .
                (trim((string) $a['suggestion']) !== ''
                    ? html_writer::div('<strong>Suggestion:</strong> ' . nl2br(s($a['suggestion'])), 'mt-2') : ''),
                'tp-fbr-detail'
            ),
            ['class' => 'tp-fbr-response']
        );
    }
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h5', 'Individual Responses', ['class' => 'mb-0']) .
            html_writer::link($csvurl, '⬇ Download CSV', ['class' => 'btn btn-sm btn-outline-primary']),
            'd-flex justify-content-between align-items-center mb-2'
        ) . $items,
        'tp-fbr-panel'
    );
}

function tp_feedback_report_styles() {
    echo <<<CSS
<style>
    .tp-fbr { max-width: 1100px; margin: 0 auto; }
    .tp-fbr h5 { font-weight: 600; margin-bottom: 14px; }
    .tp-fbr .is-high { --c: #1e8e3e; } .tp-fbr .is-good { --c: #1a73e8; }
    .tp-fbr .is-mid  { --c: #e37400; } .tp-fbr .is-low  { --c: #d93025; }
    .tp-fbr-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 18px; }
    .tp-fbr-card { background: #fff; border: 1px solid #e3e6ea; border-radius: 10px; padding: 16px 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .tp-fbr-card-value { font-size: 1.7rem; font-weight: 700; color: var(--c, #1f2328); line-height: 1.2; }
    .tp-fbr-card-value small { font-size: .9rem; color: #6a737d; font-weight: 400; }
    .tp-fbr-card-label { color: #6a737d; font-size: .85rem; text-transform: uppercase; letter-spacing: .03em; margin-top: 4px; }
    .tp-fbr-panel { background: #fff; border: 1px solid #e3e6ea; border-radius: 10px; padding: 18px 20px; margin-bottom: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .tp-fbr-stack { display: flex; height: 28px; border-radius: 14px; overflow: hidden; background: #eef0f2; }
    .tp-fbr-seg { color: #fff; font-size: .8rem; font-weight: 600; display: flex; align-items: center; justify-content: center; }
    .seg-sat { background: #1e8e3e; } .seg-minor { background: #f9ab00; } .seg-not { background: #d93025; }
    .tp-fbr-legend { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-top: 10px; font-size: .9rem; }
    .tp-fbr-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
    .tp-fbr-section { font-weight: 600; color: #3c4043; background: #f6f8fa; border-radius: 6px; padding: 6px 10px; margin: 14px 0 6px; }
    .tp-fbr-item { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 16px; align-items: center;
        padding: 7px 4px; border-bottom: 1px dashed #eceef0; }
    .tp-fbr-item:last-child { border-bottom: 0; }
    .tp-fbr-sno { color: #6a737d; margin-right: 6px; }
    .tp-fbr-item-bar { display: flex; align-items: center; gap: 10px; }
    .tp-fbr-track { flex: 1; height: 10px; background: #eef0f2; border-radius: 5px; overflow: hidden; cursor: help; }
    .tp-fbr-fill { height: 100%; background: var(--c); border-radius: 5px; }
    .tp-fbr-score { width: 34px; text-align: right; font-weight: 700; color: var(--c, #6a737d); }
    .tp-fbr-quotes { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .tp-fbr-quote { border-left: 4px solid #1a73e8; background: #f8fafd; border-radius: 6px; padding: 12px 14px; }
    .tp-fbr-quote-text { font-style: italic; }
    .tp-fbr-quote-by { color: #6a737d; font-size: .85rem; margin-top: 6px; }
    .tp-fbr-response { border: 1px solid #e3e6ea; border-radius: 8px; margin-bottom: 8px; }
    .tp-fbr-response summary { cursor: pointer; padding: 10px 14px; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .tp-fbr-response[open] summary { border-bottom: 1px solid #e3e6ea; background: #f8f9fa; }
    .tp-fbr-pill { margin-left: auto; font-size: .78rem; color: #fff; border-radius: 12px; padding: 2px 10px; background: #9aa0a6; }
    .tp-fbr-detail { padding: 12px 16px; }
    .tp-fbr-dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 16px; margin-bottom: 10px; }
    .tp-fbr-dl dt { color: #6a737d; font-weight: 500; } .tp-fbr-dl dd { margin: 0; }
    .tp-fbr-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .tp-fbr-chip { font-size: .8rem; border-radius: 12px; padding: 2px 9px; background: #eef0f2;
        color: var(--c, #3c4043); border: 1px solid currentColor; }
    @media (max-width: 700px) {
        .tp-fbr-item { grid-template-columns: 1fr; gap: 6px; }
        .tp-fbr-dl { grid-template-columns: 1fr; }
        .tp-fbr-pill { margin-left: 0; }
    }
</style>
CSS;
}

// =============================================================================
// ADMIN REPORT (+ CSV download)
// =============================================================================
if ($action === 'report') {
    require_capability('moodle/site:config', context_system::instance());

    $records = $DB->get_records('researchproject_feedback', ['instanceid' => $instance->id], 'timemodified DESC');

    $byrole = [];   // Flat rows (CSV).
    $raw    = [];   // Structured answers (dashboard).
    foreach ($records as $r) {
        if (!isset($roles[$r->role])) {
            continue;
        }
        $user = core_user::get_user($r->userid);
        $name = $user ? fullname($user) : '#' . $r->userid;
        $answers = json_decode($r->responses ?? '', true) ?: [];
        $answers += ['info' => [], 'ratings' => [], 'overall' => '', 'suggestion' => ''];
        $byrole[$r->role][] = ['Submitted by' => $name,
                               'Date' => userdate($r->timemodified, '%d %b %Y %H:%M')]
                            + tp_feedback_report_columns(tp_get_feedback_form($r->role), $answers);
        $raw[$r->role][] = ['name' => $name, 'time' => $r->timemodified, 'answers' => $answers];
    }

    if ($download && isset($roles[$download])) {
        require_once($CFG->libdir . '/csvlib.class.php');
        $rows = $byrole[$download] ?? [];
        $csv = new csv_export_writer();
        $csv->set_filename('tp_feedback_' . $download . '_' . $instance->id);
        if ($rows) {
            $csv->add_data(array_keys(reset($rows)));
            foreach ($rows as $row) {
                $csv->add_data(array_values($row));
            }
        }
        $csv->download_file();
        exit;
    }

    echo $OUTPUT->header();
    tp_feedback_report_styles();
    echo html_writer::start_div('tp-fbr');
    echo html_writer::div(
        html_writer::tag('h3', get_string('feedback_report', 'mod_researchproject'), ['class' => 'mb-0']) .
        html_writer::link($viewurl, '← Back', ['class' => 'btn btn-sm btn-outline-secondary']),
        'd-flex justify-content-between align-items-center mb-3'
    );

    if (empty($raw)) {
        echo $OUTPUT->notification(get_string('feedback_none', 'mod_researchproject'), 'info');
        echo html_writer::end_div();
        echo $OUTPUT->footer();
        exit;
    }

    // Role tabs (Bootstrap 4 and 5 attributes, so it works on either theme).
    $first = null;
    $tabs  = '';
    foreach ($roles as $role => $rolelabel) {
        $count  = count($raw[$role] ?? []);
        $first  = $first ?? ($count ? $role : null);
        $active = $role === $first;
        $tabs  .= html_writer::tag('li', html_writer::link('#tp-fbr-' . $role,
            s($rolelabel) . ' ' . html_writer::span($count, 'badge badge-pill bg-secondary badge-secondary ml-1'),
            ['class' => 'nav-link' . ($active ? ' active' : ''), 'data-toggle' => 'tab', 'data-bs-toggle' => 'tab', 'role' => 'tab']
        ), ['class' => 'nav-item']);
    }
    echo html_writer::tag('ul', $tabs, ['class' => 'nav nav-tabs mb-3', 'role' => 'tablist']);

    echo html_writer::start_div('tab-content');
    foreach ($roles as $role => $rolelabel) {
        echo html_writer::start_div('tab-pane fade' . ($role === $first ? ' show active' : ''),
            ['id' => 'tp-fbr-' . $role, 'role' => 'tabpanel']);
        if (empty($raw[$role])) {
            echo $OUTPUT->notification('No ' . $rolelabel . ' feedback yet.', 'info');
        } else {
            tp_feedback_render_role_report(tp_get_feedback_form($role), $raw[$role],
                new moodle_url($pageurl, ['download' => $role]));
        }
        echo html_writer::end_div();
    }
    echo html_writer::end_div();   // tab-content
    echo html_writer::end_div();   // tp-fbr
    echo $OUTPUT->footer();
    exit;
}

// =============================================================================
// FILL OWN FEEDBACK
// =============================================================================
$role = tp_get_user_role($instance, $context);
if (!isset($roles[$role])) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('feedback_title', 'mod_researchproject'));
}
if ($role === 'student') {
    // Trainee teachers must be enrolled in BOTH the certificate and the
    // project-submission course.
    tp_require_student_in_both_courses($course, $USER->id);
}

$form     = tp_get_feedback_form($role);
$scale    = tp_feedback_rating_scale();
$overall  = tp_feedback_overall_options();
$existing = tp_get_feedback($instance->id, $USER->id, $role);

// Section A is read-only and always rebuilt from Moodle data; only fields
// marked 'editable' keep the user's own (previous) answer.
$prefill = tp_feedback_prefill($role, $instance, $course, $USER);
foreach ($form['info'] as $f) {
    if ($f['type'] === 'static') {
        $prefill[$f['name']] = $f['value'];
    }
}

$values = $existing ? (json_decode($existing->responses ?? '', true) ?: []) : [];
$values += ['info' => [], 'ratings' => [], 'overall' => '', 'suggestion' => ''];
$values['info'] = tp_feedback_build_info($form, $prefill, $values['info']);

/**
 * Section A: Moodle values for read-only fields, $userinfo for editable ones.
 */
function tp_feedback_build_info(array $form, array $prefill, array $userinfo) {
    $info = [];
    foreach ($form['info'] as $f) {
        $n = $f['name'];
        $info[$n] = !empty($f['editable'])
            ? trim((string) ($userinfo[$n] ?? ''))
            : ($prefill[$n] ?? ($f['type'] === 'coursecodes' ? [] : ''));
    }
    return $info;
}

$errors = [];

if (optional_param('cancel', '', PARAM_RAW)) {
    redirect($viewurl);
}

if (data_submitted() && confirm_sesskey()) {
    $postinfo    = optional_param_array('info', [], PARAM_RAW);
    $postratings = optional_param_array('ratings', [], PARAM_INT);

    // Only 'editable' Section A fields are read from the request; everything
    // else is taken from Moodle, whatever was posted.
    $cleaninfo = [];
    foreach ($form['info'] as $f) {
        if (!empty($f['editable'])) {
            $cleaninfo[$f['name']] = clean_param($postinfo[$f['name']] ?? '', PARAM_TEXT);
        }
    }
    $values = ['info' => tp_feedback_build_info($form, $prefill, $cleaninfo), 'ratings' => []];

    foreach ($form['info'] as $f) {
        if (empty($f['editable'])) {
            continue;
        }
        $n = $f['name'];
        $v = $values['info'][$n];
        if ($f['type'] === 'number' && $v !== '' && (!is_numeric($v) || $v < 0)) {
            $errors['info_' . $n] = 'Please enter a valid number.';
        } else if (!empty($f['required']) && $v === '') {
            $errors['info_' . $n] = get_string('required');
        }
    }

    foreach ($form['sections'] as $section) {
        foreach ($section['items'] as $key => $label) {
            $r = $postratings[$key] ?? 0;
            if (isset($scale[$r])) {
                $values['ratings'][$key] = $r;
            } else {
                $errors['rating_' . $key] = true;
            }
        }
    }

    $values['overall'] = optional_param('overall', '', PARAM_ALPHAEXT);
    if (!isset($overall[$values['overall']])) {
        $values['overall'] = '';
        $errors['overall'] = get_string('required');
    }
    $values['suggestion'] = trim(optional_param('suggestion', '', PARAM_TEXT));

    if (!$errors) {
        $record = (object) [
            'instanceid'   => $instance->id,
            'userid'       => $USER->id,
            'role'         => $role,
            'responses'    => json_encode($values),
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('researchproject_feedback', $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record('researchproject_feedback', $record);
        }
        redirect($viewurl, get_string('feedback_saved', 'mod_researchproject'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
?>
<style>
    .tp-feedback { max-width: 960px; margin: 0 auto; background: #fff; color: #1a1a1a;
        border: 1px solid #d0d7de; padding: 32px 40px; font-family: "Times New Roman", Times, serif; }
    .tp-feedback .fb-head { text-align: center; margin-bottom: 24px; }
    .tp-feedback .fb-head h3 { margin: 0; font-weight: bold; }
    .tp-feedback .fb-head h4 { margin: 2px 0 10px; font-weight: normal; }
    .tp-feedback .fb-head h5 { margin: 0; font-weight: bold; }
    .tp-feedback h6 { font-weight: bold; font-size: 1.05rem; margin: 18px 0 10px; }
    .tp-feedback .fb-info { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .tp-feedback .fb-info th, .tp-feedback .fb-info td { border: 1px solid #c8ced6; padding: 7px 10px; vertical-align: middle; }
    .tp-feedback .fb-info th { width: 42%; background: #f6f8fa; font-weight: 600; }
    .tp-feedback .fb-info td { background: #fbfbfb; }
    .tp-feedback .fb-grid { width: 100%; border-collapse: collapse; margin: 8px 0 20px; }
    .tp-feedback .fb-grid th, .tp-feedback .fb-grid td { border: 1px solid #555; padding: 6px 8px; vertical-align: middle; }
    .tp-feedback .fb-grid thead th { background: #f2f2f2; text-align: center; font-size: .92rem; }
    .tp-feedback .fb-grid .fb-section td { background: #e9eef5; font-weight: bold; }
    .tp-feedback .fb-grid td.fb-sno { width: 44px; text-align: center; }
    .tp-feedback .fb-grid td.fb-opt { width: 92px; text-align: center; }
    .tp-feedback .fb-grid td.fb-opt input { transform: scale(1.25); cursor: pointer; }
    .tp-feedback .fb-grid tr.fb-missing td { background: #fdecea; }
    .tp-feedback .fb-thanks { text-align: center; font-style: italic; font-weight: bold; margin-top: 24px; }
    .tp-feedback .fb-err { color: #c0392b; font-size: .875rem; }
    @media (max-width: 700px) {
        .tp-feedback { padding: 16px; }
        .tp-feedback .fb-grid td.fb-opt { width: auto; }
    }
</style>

<form method="post" action="<?php echo $pageurl->out(false); ?>" class="tp-feedback">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">

    <div class="fb-head">
        <h3>Allama Iqbal Open University</h3>
        <h4>Faculty of Education</h4>
        <h5><?php echo s($form['title']); ?></h5>
    </div>

    <?php if ($errors) : ?>
        <div class="alert alert-danger">Please complete all required fields and rate every statement.</div>
    <?php endif; ?>

    <!-- Section A: Preliminary Information -->
    <h6>Section A: Preliminary Information</h6>
    <table class="fb-info">
        <?php foreach ($form['info'] as $f) :
            $n   = $f['name'];
            $id  = 'tp-fb-' . $n;
            $val = $values['info'][$n];
            ?>
            <tr>
                <th scope="row"><?php echo !empty($f['editable'])
                    ? '<label for="' . $id . '" class="mb-0">' . s($f['label']) . ' <span class="text-danger">*</span></label>'
                    : s($f['label']); ?></th>
                <td>
                    <?php if (!empty($f['editable'])) : ?>
                        <div class="d-flex align-items-center">
                            <input type="<?php echo $f['type'] === 'number' ? 'number' : 'text'; ?>" min="0"
                                name="info[<?php echo $n; ?>]" id="<?php echo $id; ?>" value="<?php echo s($val); ?>"
                                class="form-control form-control-sm" style="max-width: 140px;" required>
                            <?php if (!empty($f['suffix'])) : ?><span class="ml-2"><?php echo s($f['suffix']); ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($errors['info_' . $n])) : ?><div class="fb-err"><?php echo s($errors['info_' . $n]); ?></div><?php endif; ?>

                    <?php elseif ($f['type'] === 'radio') : ?>
                        <?php foreach ($f['options'] as $ov => $ol) : ?>
                            <span class="mr-3"><?php echo $val === $ov ? '☑' : '☐'; ?> <?php echo s($ol); ?></span>
                        <?php endforeach; ?>

                    <?php else :
                        $text = is_array($val) ? implode(', ', $val) : (string) $val;
                        $text .= ($text !== '' && !empty($f['suffix'])) ? ' ' . $f['suffix'] : '';
                        echo $text !== '' ? s($text) : '<span class="text-muted">—</span>';
                    endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p class="mt-3">Please rate each statement based on your experience.</p>

    <!-- Rating grid -->
    <div class="table-responsive">
    <table class="fb-grid">
        <thead>
            <tr>
                <th>S#</th>
                <th>Item</th>
                <?php foreach ($scale as $sv => $sl) : ?>
                    <th><?php echo s($sl); ?><?php echo $form['shownumbers'] ? '<br>(' . sprintf('%02d', $sv) . ')' : ''; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $sno = 1; foreach ($form['sections'] as $section) : ?>
                <tr class="fb-section"><td colspan="<?php echo 2 + count($scale); ?>"><?php echo s($section['title']); ?></td></tr>
                <?php foreach ($section['items'] as $key => $label) : ?>
                    <tr class="<?php echo !empty($errors['rating_' . $key]) ? 'fb-missing' : ''; ?>">
                        <td class="fb-sno"><?php echo $sno++; ?></td>
                        <td><?php echo s($label); ?></td>
                        <?php foreach ($scale as $sv => $sl) : ?>
                            <td class="fb-opt">
                                <input type="radio" name="ratings[<?php echo $key; ?>]" value="<?php echo $sv; ?>"
                                    aria-label="<?php echo s($label . ' — ' . $sl); ?>"
                                    <?php echo (int) ($values['ratings'][$key] ?? 0) === $sv ? 'checked' : ''; ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Overall satisfaction -->
    <p class="font-weight-bold mb-2"><?php echo s($form['overall_label']); ?> <span class="text-danger">*</span></p>
    <?php foreach ($overall as $ov => $ol) : ?>
        <div class="form-check mb-1">
            <input class="form-check-input" type="radio" name="overall" id="tp-fb-overall-<?php echo $ov; ?>" value="<?php echo $ov; ?>"
                <?php echo $values['overall'] === $ov ? 'checked' : ''; ?>>
            <label class="form-check-label" for="tp-fb-overall-<?php echo $ov; ?>"><?php echo s($ol); ?></label>
        </div>
    <?php endforeach; ?>
    <?php if (!empty($errors['overall'])) : ?><div class="fb-err"><?php echo s($errors['overall']); ?></div><?php endif; ?>

    <!-- Suggestion -->
    <p class="font-weight-bold mt-4 mb-2"><label for="tp-fb-suggestion">Suggestion for Improvement (Optional):</label></p>
    <textarea name="suggestion" id="tp-fb-suggestion" rows="5" class="form-control"><?php echo s($values['suggestion']); ?></textarea>

    <p class="fb-thanks"><?php echo s($form['thanks']); ?></p>

    <div class="text-center mt-3">
        <button type="submit" name="submitfeedback" value="1" class="btn btn-primary"><?php echo get_string('feedback_submit', 'mod_researchproject'); ?></button>
        <button type="submit" name="cancel" value="1" class="btn btn-secondary ml-2" formnovalidate><?php echo get_string('cancel'); ?></button>
    </div>
</form>
<?php
echo $OUTPUT->footer();
