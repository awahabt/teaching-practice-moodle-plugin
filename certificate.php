<?php
/**
 * certificate.php
 *
 * Displays the Teaching Practice Completion Certificate.
 * All data is pulled dynamically from the database.
 *
 * URL parameters:
 *   id        = course module ID
 *   studentid = student whose certificate to show
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/teachingpractice/lib.php');

$cmid      = required_param('id',        PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'teachingpractice');
$instance = $DB->get_record('teachingpractice', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/teachingpractice:viewcertificate', $context);

$student  = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
$performa = tp_get_performa($instance->id, $studentid);

$PAGE->set_url(new moodle_url('/mod/teachingpractice/certificate.php', [
    'id'        => $cmid,
    'studentid' => $studentid,
]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_title('Teaching Practice Certificate — ' . fullname($student));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('popup');
$PAGE->add_body_class('tp-certificate-page');

// Determine viewer role.
$viewer_role = tp_get_user_role($instance, $context);

// Prevent student from viewing other students' certificates.
if ($viewer_role === 'student' && $USER->id != $student->id) {
    throw new moodle_exception('nopermissiontoviewfortrainee', 'mod_teachingpractice');
}

// Find matching course to check student project pass/fail status.
$matching_course = tp_find_matching_course($course->shortname, $course->id);
$student_passes  = $matching_course
    ? tp_student_passes_course($student->id, $matching_course->id)
    : false;
$student_avg_pct = $matching_course
    ? tp_get_student_average_grade_percentage($student->id, $matching_course->id)
    : null;

// ── Gate 1: Evaluations not yet complete ─────────────────────────────────────
if (!$performa || $performa->status !== TP_STATUS_COMPLETED) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('error_not_complete', 'mod_teachingpractice'), 'warning'
    );
    echo html_writer::link(
        new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]),
        '← Back',
        ['class' => 'btn btn-secondary mt-2']
    );
    echo $OUTPUT->footer();
    exit;
}

// ── Gate 2: Student has not met the passing grade criteria ───────────────────
// Only applies when viewed by the student; CT / HT can always view certificates.
if ($viewer_role === 'student' && !$student_passes) {
    echo $OUTPUT->header();
    if ($student_avg_pct === null) {
        // Assignments not yet graded.
        echo $OUTPUT->notification(
            'The certificate cannot be shown yet because your assignments have not been graded. ' .
            'Please ensure all your project assignments are submitted and graded before the certificate becomes available.',
            'warning'
        );
    } else {
        // Graded but below passing mark.
        echo html_writer::div(
            html_writer::tag('h4', '❌ Certificate Not Available — Passing Mark Not Met') .
            html_writer::tag('p',
                'Your average mark across all assignments is <strong>' . number_format($student_avg_pct, 1) . '%</strong>. ' .
                'The minimum passing mark required for a certificate is <strong>' . TP_PASSING_PERCENTAGE . '%</strong>. ' .
                'Please contact your instructor for further guidance.'
            ),
            'alert alert-danger mb-3'
        );
    }
    echo html_writer::link(
        new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]),
        '← Back',
        ['class' => 'btn btn-secondary mt-2']
    );
    echo $OUTPUT->footer();
    exit;
}


$certificate = $DB->get_record('teachingpractice_certificate', ['performaid' => $performa->id]);

if (!$certificate) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('error_no_certificate', 'mod_teachingpractice'), 'error'
    );
    echo $OUTPUT->footer();
    exit;
}

// Format dates
$start_date  = date('d F Y', $performa->start_date);
$end_date    = date('d F Y', $performa->end_date);
$issued_date = date('d F Y', $certificate->issued_date);

// Build subjects list (exclude empty values)
$decoded = json_decode($performa->subject_1, true);
if (is_array($decoded)) {
    $subjects = $decoded;
} else {
    $subjects = array_filter([
        $performa->subject_1,
        $performa->subject_2,
        $performa->subject_3,
    ]);
}

// Extract course code and school name from fullname if formatted using pipes.
$extracted = tp_extract_course_code_and_school_name($course, $matching_course);
$display_coursecode = !empty($extracted['coursecode']) ? $extracted['coursecode'] : ($matching_course ? $matching_course->shortname : $course->shortname);
$display_schoolname = !empty($extracted['schoolname']) ? $extracted['schoolname'] : $performa->school_name;


$signaturename = get_config('mod_teachingpractice', 'signature_name');
if (empty($signaturename)) {
    $signaturename = '';
}

$signaturetitle = get_config('mod_teachingpractice', 'signature_title');
if (empty($signaturetitle)) {
    $signaturetitle = '';
}

$signatureurl = null;
$fs = get_file_storage();
$systemcontext = context_system::instance();
$files = $fs->get_area_files($systemcontext->id, 'mod_teachingpractice', 'signature_image', 0, 'id DESC', false);

if (!empty($files)) {
    $file = reset($files);
    $signatureurl = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

if (empty($signatureurl)) {
    $signatureurl = $OUTPUT->image_url('signature', 'mod_teachingpractice');
}

echo $OUTPUT->header();
?>

<style>
/* ── Certificate wrapper ─────────────────────────────────────── */
.tp-certificate {
    border: 2px double #0d7215ff;
    padding: 44px 56px;
    max-width: 870px;
    margin: 0 auto 30px;
    font-family: "Times New Roman", Times, serif;
    background: #ffffff;
    color: #1a1a1a;
    box-shadow: 0 4px 24px rgba(0,0,0,0.10);
}

/* ── Header ──────────────────────────────────────────────────── */
.tp-certificate .cert-header {
    text-align: center;
    border-bottom: 2px solid #0d7215ff;
    padding-bottom: 18px;
    margin-bottom: 22px;
}
.tp-certificate .cert-header h2 {
    color: #0d7215ff;
    font-size: 2rem;
    margin: 0 0 4px;
    letter-spacing: 1px;
}
.tp-certificate .cert-header p {
    margin: 2px 0;
    font-size: 0.90rem;
    color: #555;
}

/* ── Certificate title ───────────────────────────────────────── */
.tp-certificate .cert-title {
    text-align: center;
    font-size: 1.50rem;
    font-weight: bold;
    color: #0d7215ff;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 18px 0 26px;
    text-decoration: underline;
}

/* ── Body text ───────────────────────────────────────────────── */
.tp-certificate .cert-body {
    font-size: 1rem;
    line-height: 1.95;
    text-align: justify;
}
.tp-certificate .cert-body strong { color: #0d7215ff; }

/* ── Subject list ────────────────────────────────────────────── */
.tp-certificate .cert-subjects {
    margin: 10px 0 10px 30px;
}
.tp-certificate .cert-subjects li { margin: 4px 0; }

/* ── Evaluation tables ───────────────────────────────────────── */
.tp-certificate .eval-table {
    width: 100%;
    border-collapse: collapse;
    margin: 16px 0;
    font-size: 0.94rem;
}
.tp-certificate .eval-table th {
    background: #0d7215ff;
    color: #fff;
    padding: 8px 12px;
    text-align: left;
}
.tp-certificate .eval-table td {
    padding: 7px 12px;
    border: 1px solid #ccc;
}
.tp-certificate .eval-table tr:nth-child(even) td {
    background: #f5f8ff;
}

/* ── Recommendation box ──────────────────────────────────────── */
.tp-certificate .cert-recommendation {
    background: #eef3ff;
    border-left: 4px solid #0d7215ff;
    padding: 10px 16px;
    margin: 18px 0;
    font-size: 1rem;
}

/* ── Certificate number ──────────────────────────────────────── */
.tp-certificate .cert-no {
    text-align: right;
    font-size: 0.88rem;
    color: #666;
    margin-bottom: 8px;
}

/* ── Signatures ──────────────────────────────────────────────── */
.tp-certificate .cert-signatures {
    display: flex;
    justify-content: end;
    margin-top: 20px;
    margin-bottom: 10px;
    text-align: center;
}
.tp-certificate .cert-signature-col {
    width: 220px;
}
.tp-certificate .signature-space {
    height: 75px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}
.tp-certificate .signature-line {
    border-bottom: 1px dashed #ccc;
    width: 100%;
    margin-bottom: 10px;
}
.tp-certificate .signature-img {
    max-height: 70px;
    max-width: 200px;
    object-fit: contain;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    pointer-events: none;
    -webkit-user-drag: none;
}
.tp-certificate .signature-label {
    border-top: 1px solid #ccc;
    margin-top: 5px;
    padding-top: 5px;
    font-size: 0.9rem;
    color: #333;
}

/* ── Footer ──────────────────────────────────────────────────── */
.tp-certificate .cert-footer {
    margin-top: 34px;
    text-align: center;
    font-size: 0.87rem;
    color: #777;
    border-top: 1px solid #ccc;
    padding-top: 14px;
}

/* ── Print styles — certificate only, no Moodle chrome ───────── */
@media print {
    @page {
        margin: 12mm;
        size: A4 portrait;
    }

    html, body {
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    body * {
        visibility: hidden;
    }

    .no-print,
    .no-print * {
        display: none !important;
        visibility: hidden !important;
    }

    .tp-certificate,
    .tp-certificate * {
        visibility: visible;
    }

    .tp-certificate {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 24px 32px !important;
        border: 4px double #0d7215ff !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .tp-certificate .eval-table th {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>

<!-- Action buttons (hidden on print) -->
<div class="no-print mb-3">
    <button type="button" onclick="window.print();" class="btn btn-primary mr-2">
        🖨️ Print Certificate
    </button>
    <a href="<?php echo (new moodle_url('/mod/teachingpractice/view.php', ['id' => $cmid]))->out(); ?>"
       class="btn btn-secondary">
        ← Back
    </a>
</div>

<!-- ══════════════════════════════════════════════════════════════
     CERTIFICATE
══════════════════════════════════════════════════════════════ -->
<div class="tp-certificate">

    <!-- Certificate number top-right -->
    <div class="cert-no">
        Certificate No:&nbsp;<strong><?php echo s($certificate->certificate_no); ?></strong>
        &nbsp;|&nbsp; Date Issued: <?php echo $issued_date; ?>
    </div>

    <!-- University header -->
    <div class="cert-header">
        <h2>Allama Iqbal Open University</h2>
        <p>Islamabad, Pakistan</p>
        <p>Department of Education</p>
    </div>

    <!-- Title -->
    <div class="cert-title">Teaching Practice Completion Certificate</div>

    <!-- Main body -->
    <div class="cert-body">
        <p>
            This is to certify that
            <strong><?php echo fullname($student); ?></strong>,
            Registration No.&nbsp;<strong><?php echo s($student->email); ?></strong>,
            has successfully completed the Teaching Practice (Course Code:&nbsp;<?php echo s($display_coursecode); ?>) at
            <strong><?php echo s($display_schoolname); ?></strong>
            from <strong><?php echo $start_date; ?></strong>
            to&nbsp;<strong><?php echo $end_date; ?></strong>,
            <!-- with timings
            <strong>
                <?php echo s($performa->morning_time); ?>
                &nbsp;–&nbsp;
                <?php echo s($performa->afternoon_time); ?>
            </strong>, -->
            completing a total of
            <strong><?php echo (int)$performa->days_count; ?>&nbsp;days</strong>
            of teaching practice under the supervision of
            <strong><?php echo s($performa->ct_teacher_name); ?></strong>.
        </p>

        <?php if (!empty($subjects)): ?>
        <p>During the teaching practice, the trainee teacher taught the following subject(s):</p>
        <ul class="cert-subjects">
            <?php foreach ($subjects as $subj): ?>
                <li><?php echo s($subj); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Cooperating Teacher Evaluation table -->
    <p>
        <strong>Cooperating Teacher Evaluation</strong>
        <span class="text-muted" style="font-size:0.9rem;">
            (Evaluated by: <?php echo s($performa->ct_teacher_name); ?>
            on <?php echo date('d F Y', $performa->ct_submitted_date); ?>)
        </span>
    </p>
    <table class="eval-table">
        <thead>
            <tr><th>Criterion</th><th>Rating</th></tr>
        </thead>
        <tbody>
            <tr><td>Subject Knowledge</td>
                <td><?php echo s(tp_rating_label($performa->ct_subject_knowledge)); ?></td></tr>
            <tr><td>Lesson Planning &amp; Preparation</td>
                <td><?php echo s(tp_rating_label($performa->ct_lesson_planning)); ?></td></tr>
            <tr><td>Instructional Delivery</td>
                <td><?php echo s(tp_rating_label($performa->ct_instructional_delivery)); ?></td></tr>
            <tr><td>Classroom Management</td>
                <td><?php echo s(tp_rating_label($performa->ct_classroom_management)); ?></td></tr>
            <tr><td>Assessment &amp; Feedback</td>
                <td><?php echo s(tp_rating_label($performa->ct_assessment_feedback)); ?></td></tr>
            <tr><td>Professionalism &amp; Communication</td>
                <td><?php echo s(tp_rating_label($performa->ct_professionalism)); ?></td></tr>
        </tbody>
    </table>
    <?php if (!empty($performa->ct_remarks)): ?>
        <p><em>CT Remarks: <?php echo s($performa->ct_remarks); ?></em></p>
    <?php endif; ?>

    <!-- Head Teacher Evaluation table -->
    <p>
        <strong>Head Teacher Evaluation</strong>
        <span class="text-muted" style="font-size:0.9rem;">
            (Evaluated by: <?php echo s($performa->ht_teacher_name); ?>
            on <?php echo date('d F Y', $performa->ht_submitted_date); ?>)
        </span>
    </p>
    <table class="eval-table">
        <thead>
            <tr><th>Criterion</th><th>Rating</th></tr>
        </thead>
        <tbody>
            <tr><td>Attendance and Regularity</td>
                <td><?php echo s(tp_rating_label($performa->ht_attendance)); ?></td></tr>
            <tr><td>Punctuality</td>
                <td><?php echo s(tp_rating_label($performa->ht_punctuality)); ?></td></tr>
            <tr><td>Participation in Teaching–Learning Activities</td>
                <td><?php echo s(tp_rating_label($performa->ht_participation_teaching)); ?></td></tr>
            <tr><td>Participation in Co-curricular Activities</td>
                <td><?php echo s(tp_rating_label($performa->ht_participation_cocurr)); ?></td></tr>
            <tr><td>Professional Conduct and Collaboration</td>
                <td><?php echo s(tp_rating_label($performa->ht_professional_conduct)); ?></td></tr>
        </tbody>
    </table>
    <?php if (!empty($performa->ht_remarks)): ?>
        <p><em>HT Remarks: <?php echo s($performa->ht_remarks); ?></em></p>
    <?php endif; ?>

    <!-- Overall Recommendation -->
    <div class="cert-recommendation">
        <strong>Overall Recommendation:</strong>
        <?php echo s(tp_recommendation_label($performa->ht_overall_recommendation)); ?>
    </div>

    <!-- Signatures -->
    <div class="cert-signatures">
        <div class="cert-signature-col">
            <div class="signature-space">
                <img src="<?php echo s($signatureurl); ?>" alt="Digital Signature" class="signature-img"
                     draggable="false"
                     oncontextmenu="return false;"
                     ondragstart="return false;">
            </div>
            <div class="signature-label">
                <strong><?php echo s($signaturename); ?></strong><br>
                <?php echo s($signaturetitle); ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="cert-footer">
        This is a digitally signed certificate.<br>
        Issued by <strong>Allama Iqbal Open University</strong> - School Management System for Teaching Practice
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var sigImg = document.querySelector('.signature-img');
    if (sigImg) {
        sigImg.addEventListener('contextmenu', function(e) { e.preventDefault(); return false; });
        sigImg.addEventListener('dragstart', function(e) { e.preventDefault(); return false; });
        sigImg.addEventListener('mousedown', function(e) { if (e.button === 2) e.preventDefault(); });
        sigImg.addEventListener('touchstart', function(e) { e.preventDefault(); }, { passive: false });
        sigImg.setAttribute('oncontextmenu', 'return false;');
    }
});
</script>

<?php
echo $OUTPUT->footer();
