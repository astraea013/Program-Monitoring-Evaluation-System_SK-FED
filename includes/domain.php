<?php
/**
 * Business rules shared across roles: uploads, inventory stock status,
 * budget utilisation, evaluation forms, participation and summaries.
 * Derived columns (stock_status, utilised_amount, remaining_amount) are
 * always recomputed here, inside the caller's transaction.
 */

require_once __DIR__ . '/helpers.php';

/* ------------------------------------------------------------- uploads */

const DOC_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
];
const ID_TYPES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'pdf' => ['application/pdf']];
const PHOTO_TYPES = ['jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png']];

const DOC_CATEGORIES = [
    'resolution' => 'Resolutions',
    'minutes'    => 'Minutes of Meetings',
    'report'     => 'Accomplishment Reports',
    'letter'     => 'Letters',
    'proposal'   => 'Budget Proposals',
    'other'      => 'Other Documents',
];

/**
 * Validate and store one uploaded file by extension AND detected MIME type,
 * under a random name. Returns ['path' => 'subdir/name', 'size' => int,
 * 'name' => original] or throws RuntimeException with a user-facing message.
 */
function store_upload(?array $file, string $subdir, array $allowed): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose a file to upload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The upload failed. Try again with a smaller file.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('The file is larger than 5 MB.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new RuntimeException('That file type is not allowed. Allowed: ' . strtoupper(implode(', ', array_keys($allowed))) . '.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, $allowed[$ext], true)) {
        throw new RuntimeException('The file content does not match its extension.');
    }
    $dir = UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('The upload folder is not writable.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('The file could not be saved.');
    }
    return ['path' => $subdir . '/' . $name, 'size' => (int) $file['size'], 'name' => $file['name']];
}

function delete_upload(?string $relPath): void
{
    if (!$relPath || str_contains($relPath, '..')) {
        return;
    }
    $full = UPLOAD_DIR . '/' . $relPath;
    if (is_file($full)) {
        unlink($full);
    }
}

function file_size_label(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

/* ----------------------------------------------------------- inventory */

/*
 * quantity_on_hand is the barangay's total holding of an item;
 * quantity_released is what is currently issued out. Available is the
 * difference. Status is recomputed from available against reorder_level.
 */
function refresh_item_status(int $itemId): void
{
    q("UPDATE inventory_items
          SET stock_status = CASE
                WHEN quantity_on_hand - quantity_released <= 0 THEN 'out of stock'
                WHEN quantity_on_hand - quantity_released <= reorder_level THEN 'low stock'
                ELSE 'available' END
        WHERE item_id = ?", [$itemId]);
}

/* -------------------------------------------------------------- budget */

/** Recompute a program budget's utilised amount from its expense rows. */
function refresh_utilised(int $programBudgetId): void
{
    q('UPDATE program_budgets pb
          SET utilised_amount = (SELECT COALESCE(SUM(amount), 0) FROM budget_expenses WHERE program_budget_id = pb.program_budget_id)
        WHERE program_budget_id = ?', [$programBudgetId]);
}

function current_allocation(): ?array
{
    return q_one('SELECT * FROM budget_allocations ORDER BY date_entered DESC, allocation_id DESC LIMIT 1');
}

/* ---------------------------------------------------------- evaluation */

const DEFAULT_QUESTIONS = [
    ['Program Quality', 'The goals and objectives of this activity were clearly explained.'],
    ['Program Quality', 'The activity was well-organized, with no unnecessary delays or confusion.'],
    ['Program Quality', 'The schedule and venue made it easy for me to join.'],
    ['Implementer Quality', 'The facilitators were well-prepared and knowledgeable.'],
    ['Implementer Quality', 'They encouraged us to participate actively.'],
    ['Implementer Quality', 'They answered our questions properly.'],
    ['Perceived Benefits', 'I now understand the issue or topic better than before.'],
    ['Perceived Benefits', 'I gained something useful that I can apply in real life.'],
    ['Perceived Benefits', 'I am more willing to join the next SK activity.'],
];

const RATING_LABELS = [1 => 'Strongly Disagree', 2 => 'Disagree', 3 => 'Neutral', 4 => 'Agree', 5 => 'Strongly Agree'];

/** Every activity gets an evaluation form with the default questions. */
function create_default_form(int $activityId, string $activityTitle): int
{
    q('INSERT INTO evaluation_forms (activity_id, form_title, is_open) VALUES (?, ?, 1)', [$activityId, 'Evaluation - ' . $activityTitle]);
    $formId = (int) db()->lastInsertId();
    $order = 1;
    foreach (DEFAULT_QUESTIONS as [$section, $text]) {
        q('INSERT INTO evaluation_questions (form_id, question_text, question_type, section, sort_order) VALUES (?, ?, "rating", ?, ?)', [$formId, $text, $section, $order++]);
    }
    q('INSERT INTO evaluation_questions (form_id, question_text, question_type, section, sort_order) VALUES (?, ?, "text", "Suggestions", ?)', [$formId, 'Do you have any suggestions to make future activities better?', $order]);
    return $formId;
}

function new_qr_token(): string
{
    return bin2hex(random_bytes(16));
}

/** Absolute URL encoded in an activity's QR code. Phones open it directly. */
function scan_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url('member/scan.php') . '?t=' . rawurlencode($token);
}

/* ----------------------------------------------------- shared queries */

/**
 * Programs with their budget figures, schedule and reach. $barangayId null
 * means every barangay (federation view).
 */
function programs_overview(?int $barangayId): array
{
    $where = $barangayId === null ? '' : 'WHERE p.barangay_id = ?';
    return q_all("
        SELECT p.*, b.barangay_name,
               (SELECT MIN(a.activity_date) FROM activities a WHERE a.program_id = p.program_id) AS first_date,
               (SELECT MAX(a.activity_date) FROM activities a WHERE a.program_id = p.program_id) AS last_date,
               (SELECT COUNT(*) FROM activities a WHERE a.program_id = p.program_id) AS activity_count,
               (SELECT COALESCE(SUM(a.target_count), 0) FROM activities a WHERE a.program_id = p.program_id) AS target,
               (SELECT COUNT(DISTINCT at.profile_id) FROM attendance at JOIN activities a ON a.activity_id = at.activity_id WHERE a.program_id = p.program_id) AS reached,
               pb.program_budget_id, pb.allocated_amount, pb.utilised_amount, pb.completion_status, pb.balance_note, bp.prop_status
          FROM programs p
          JOIN barangays b ON b.barangay_id = p.barangay_id
          LEFT JOIN program_budgets pb ON pb.program_budget_id = (
                SELECT pb2.program_budget_id FROM program_budgets pb2
                  JOIN budget_proposals bp2 ON bp2.proposal_id = pb2.proposal_id
                 WHERE pb2.program_id = p.program_id
                 ORDER BY (bp2.prop_status = 'approved') DESC, pb2.program_budget_id DESC LIMIT 1)
          LEFT JOIN budget_proposals bp ON bp.proposal_id = pb.proposal_id
          $where
         ORDER BY p.program_code", $barangayId === null ? [] : [$barangayId]);
}

/** Activities with program, barangay and counts. */
function activities_overview(?int $barangayId, string $extraWhere = '', array $extraParams = []): array
{
    $conds = [];
    $params = [];
    if ($barangayId !== null) {
        $conds[] = 'p.barangay_id = ?';
        $params[] = $barangayId;
    }
    if ($extraWhere !== '') {
        $conds[] = $extraWhere;
        $params = array_merge($params, $extraParams);
    }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
    return q_all("
        SELECT a.*, p.title AS program_title, p.program_code, p.centre, p.barangay_id, b.barangay_name,
               f.form_id, f.is_open AS form_open,
               (SELECT COUNT(*) FROM attendance at WHERE at.activity_id = a.activity_id) AS attendance_count,
               (SELECT COUNT(*) FROM evaluation_responses r WHERE r.form_id = f.form_id) AS response_count
          FROM activities a
          JOIN programs p ON p.program_id = a.program_id
          JOIN barangays b ON b.barangay_id = p.barangay_id
          LEFT JOIN evaluation_forms f ON f.activity_id = a.activity_id
          $where
         ORDER BY a.activity_date, a.start_time", $params);
}

/**
 * Participation and eligibility. Counts QR-scanned attendance only, within
 * the calendar year, against the activities the member's barangay held.
 */
function participation_rows(?int $barangayId, int $year): array
{
    $where = $barangayId === null ? '' : 'AND yp.barangay_id = ?';
    $params = [$year, $year];
    if ($barangayId !== null) {
        $params[] = $barangayId;
    }
    return q_all("
        SELECT yp.profile_id, yp.full_name, yp.date_encoded, b.barangay_name, yp.user_id,
               COUNT(DISTINCT CASE WHEN at.entry_method = 'qr_scan' THEN at.activity_id END) AS attended,
               COUNT(DISTINCT CASE WHEN at.entry_method = 'manual' THEN at.activity_id END) AS manual_count,
               (SELECT COUNT(*) FROM activities a JOIN programs p ON p.program_id = a.program_id
                 WHERE p.barangay_id = yp.barangay_id AND YEAR(a.activity_date) = ? AND a.activity_date <= CURDATE()) AS held,
               MAX(at.time_scanned) AS last_attended
          FROM youth_profiles yp
          JOIN barangays b ON b.barangay_id = yp.barangay_id
          LEFT JOIN users u ON u.user_id = yp.user_id
          LEFT JOIN attendance at ON at.profile_id = yp.profile_id
               AND at.activity_id IN (SELECT a.activity_id FROM activities a WHERE YEAR(a.activity_date) = ?)
         WHERE (yp.user_id IS NULL OR u.verification_status = 'verified') $where
         GROUP BY yp.profile_id
         ORDER BY yp.full_name", $params);
}

/** Share of programs per centre that are completed or evaluated. */
function centre_completion(?int $barangayId): array
{
    $where = $barangayId === null ? '' : 'WHERE barangay_id = ?';
    $rows = q_all("SELECT centre, COUNT(*) total, SUM(status IN ('completed','evaluated')) done FROM programs $where GROUP BY centre", $barangayId === null ? [] : [$barangayId]);
    $by = [];
    foreach ($rows as $r) {
        $by[$r['centre']] = $r;
    }
    $out = [];
    foreach (CENTRES as $c) {
        $t = (int) ($by[$c]['total'] ?? 0);
        $out[] = ['label' => $c, 'value' => $t ? (int) round(((int) $by[$c]['done'] / $t) * 100) : 0, 'total' => $t];
    }
    return $out;
}

/** Average rating overall, optionally for one barangay. */
function average_rating(?int $barangayId): ?float
{
    $sql = 'SELECT AVG(ea.rating_value) FROM evaluation_answers ea
              JOIN evaluation_responses r ON r.response_id = ea.response_id
              JOIN evaluation_forms f ON f.form_id = r.form_id
              JOIN activities a ON a.activity_id = f.activity_id
              JOIN programs p ON p.program_id = a.program_id
             WHERE ea.rating_value IS NOT NULL';
    $v = $barangayId === null ? q_val($sql) : q_val($sql . ' AND p.barangay_id = ?', [$barangayId]);
    return $v === null ? null : (float) $v;
}

/** Average rating per section, grouped by a column (centre, activity, barangay). */
function section_averages(?int $barangayId, string $groupBy): array
{
    $groupCol = ['centre' => 'p.centre', 'activity' => 'a.title', 'barangay' => 'b.barangay_name'][$groupBy];
    $where = $barangayId === null ? '' : 'AND p.barangay_id = ?';
    $rows = q_all("
        SELECT $groupCol AS grp, q.section, AVG(ea.rating_value) AS avg_rating, COUNT(DISTINCT r.response_id) AS responses
          FROM evaluation_answers ea
          JOIN evaluation_questions q ON q.question_id = ea.question_id
          JOIN evaluation_responses r ON r.response_id = ea.response_id
          JOIN evaluation_forms f ON f.form_id = r.form_id
          JOIN activities a ON a.activity_id = f.activity_id
          JOIN programs p ON p.program_id = a.program_id
          JOIN barangays b ON b.barangay_id = p.barangay_id
         WHERE ea.rating_value IS NOT NULL $where
         GROUP BY grp, q.section
         ORDER BY grp", $barangayId === null ? [] : [$barangayId]);
    $out = [];
    foreach ($rows as $r) {
        $out[$r['grp']][$r['section']] = (float) $r['avg_rating'];
        $out[$r['grp']]['_responses'] = max($out[$r['grp']]['_responses'] ?? 0, (int) $r['responses']);
    }
    return $out;
}

/** Inline SVG "sun" chart of centre completion, ported from the prototype. */
function sun_chart(array $centres): string
{
    $cx = 160; $cy = 160; $maxR = 110; $minR = 22; $hubR = 30; $n = count($centres);
    $avg = $n ? (int) round(array_sum(array_column($centres, 'value')) / $n) : 0;
    $tracks = $rays = $labels = '';
    foreach ($centres as $i => $c) {
        $rad = deg2rad(($i * 360) / $n - 90);
        $l = $rad - M_PI / 2; $r = $rad + M_PI / 2;
        $tip = $minR + (($maxR - $minR) * $c['value']) / 100;
        $pt = fn ($rad1, $len, $off, $offLen) => sprintf('%.1f %.1f', $cx + $len * cos($rad1) + $offLen * cos($off), $cy + $len * sin($rad1) + $offLen * sin($off));
        $tracks .= '<path d="M ' . $pt($rad, $minR - 2, $l, 12) . ' L ' . $pt($rad, $maxR, $l, 3) . ' L ' . $pt($rad, $maxR, $r, 3) . ' L ' . $pt($rad, $minR - 2, $r, 12) . ' Z" fill="var(--border-default)"/>';
        $rays .= '<path d="M ' . $pt($rad, $minR - 2, $l, 10) . ' L ' . $pt($rad, $tip, $l, 3) . ' L ' . $pt($rad, $tip, $r, 3) . ' L ' . $pt($rad, $minR - 2, $r, 10) . ' Z" fill="#F5B60D" opacity="0.9"/>';
        $lx = $cx + ($maxR + 22) * cos($rad); $ly = $cy + ($maxR + 22) * sin($rad);
        $anchor = abs(cos($rad)) < 0.1 ? 'middle' : (cos($rad) > 0 ? 'start' : 'end');
        $words = explode(' ', str_replace(' & ', ' &amp; ', e($c['label'])));
        $lines = count($words) > 1 ? [$words[0], implode(' ', array_slice($words, 1))] : $words;
        $labels .= sprintf('<text x="%.1f" y="%.1f" text-anchor="%s" font-size="9" fill="var(--text-secondary)">', $lx, $ly - (count($lines) - 1) * 5.5, $anchor);
        foreach ($lines as $j => $ln) {
            $labels .= sprintf('<tspan x="%.1f" dy="%d">%s</tspan>', $lx, $j ? 11 : 0, $ln);
        }
        $labels .= '</text>';
    }
    return '<svg class="sun-chart" viewBox="-50 -6 420 332" role="img" aria-label="Completion by centre of participation, average ' . $avg . ' percent">'
        . $tracks . $rays
        . '<circle cx="160" cy="160" r="' . $hubR . '" fill="#10214B"/>'
        . '<text x="160" y="155" text-anchor="middle" font-family="IBM Plex Mono, monospace" font-size="14" fill="#fff">' . $avg . '%</text>'
        . '<text x="160" y="170" text-anchor="middle" font-size="8" fill="rgba(255,255,255,.6)">avg</text>'
        . $labels . '</svg>';
}

/** Horizontal bar used by reports and budget tables. */
function bar(float $percent, string $colour = 'blue'): string
{
    $p = max(0, min(100, $percent));
    return '<div class="bar"><div class="bar-fill bar-' . $colour . '" style="width:' . number_format($p, 1, '.', '') . '%"></div></div>';
}
