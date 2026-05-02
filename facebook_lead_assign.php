<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'config/config.php';
if (!defined('BASE_PATH')) define('BASE_PATH', realpath(__DIR__));
require_once BASE_PATH . '/includes/auth_validate.php';

date_default_timezone_set('Asia/Kolkata');

const SHEET_PUBLISHED_CSV = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRoCpcAbtiOlMCqLJjGgCI-UFPunwEDfr0YsUciXyJhGMWRY_PMon7veqk7B7p-2OC-MZzaWcNzBuRG/pub?gid=0&single=true&output=csv';
const CACHE_TTL = 0;

$owners = [
    "rahul", "kajal", "dilpreet", "rishabhsaini", "swati", "kirti", "bhumika",
    "neelam", "shilpa", "santosh", "deepti", "harsh", "suhrid", "adrija",
    "prajakta", "atharva", "khushi", "darshita", "vanshika", "tripti", "anamika"
];

$rowPalette = [
    '#fff7ed', '#eff6ff', '#f0fdf4', '#fdf2f8', '#fefce8',
    '#f3e8ff', '#ecfeff', '#fef2f2', '#f0f9ff', '#f9fafb'
];

$ownerPalette = [
    'rahul'        => '#fde68a',
    'kajal'        => '#bfdbfe',
    'dilpreet'     => '#c7d2fe',
    'rishabhsaini' => '#fecaca',
    'swati'        => '#bbf7d0',
    'kirti'        => '#ddd6fe',
    'bhumika'      => '#fed7aa',
    'neelam'       => '#a5f3fc',
    'shilpa'       => '#fbcfe8',
    'santosh'      => '#d9f99d',
    'deepti'       => '#f5d0fe',
    'harsh'        => '#bae6fd',
    'suhrid'       => '#fde68a',
    'adrija'       => '#c4b5fd',
    'prajakta'     => '#fca5a5',
    'atharva'      => '#86efac',
    'khushi'       => '#f9a8d4',
    'darshita'     => '#93c5fd',
    'vanshika'     => '#fdba74',
    'tripti'       => '#99f6e4',
    'anamika'      => '#e9d5ff',
];

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function norm_phone($v): string {
    $v = trim((string)$v);
    $v = preg_replace('/^[a-zA-Z]+\s*:\s*/', '', $v);
    $d = preg_replace('/\D+/', '', $v);
    if ($d === '') return '';
    if (strlen($d) > 10) $d = substr($d, -10);
    return $d;
}

function parse_lead_datetime(?string $value): ?DateTime {
    $value = trim((string)$value);
    if ($value === '') return null;

    $tz = new DateTimeZone('Asia/Kolkata');

    $formats = [
        DateTime::ATOM,
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s.uP',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d\TH:i:s',
        'Y-m-d\TH:i',
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $value);
        if ($dt instanceof DateTime) {
            $dt->setTimezone($tz);
            return $dt;
        }
    }

    try {
        $dt = new DateTime($value);
        $dt->setTimezone($tz);
        return $dt;
    } catch (Throwable $e) {
        return null;
    }
}

function get_filter_range(string $preset, string $fromInput, string $toInput): array {
    $tz = new DateTimeZone('Asia/Kolkata');
    $now = new DateTime('now', $tz);

    $from = null;
    $to = null;
    $label = '';

    if ($fromInput !== '' || $toInput !== '') {
        $from = $fromInput !== '' ? new DateTime($fromInput, $tz) : null;
        $to   = $toInput !== '' ? new DateTime($toInput, $tz) : null;
        $label = 'Custom DateTime Range';
        return [$from, $to, $label];
    }

    switch ($preset) {
        case 'today_0930_1730':
            $from = new DateTime('today 09:30', $tz);
            $to   = new DateTime('today 17:30', $tz);
            $label = 'Today 09:30 AM to 05:30 PM';
            break;
        case 'today_full':
            $from = new DateTime('today 00:00:00', $tz);
            $to   = new DateTime('today 23:59:59', $tz);
            $label = 'Today Full Day';
            break;
        case 'yesterday_full':
            $from = new DateTime('yesterday 00:00:00', $tz);
            $to   = new DateTime('yesterday 23:59:59', $tz);
            $label = 'Yesterday Full Day';
            break;
        case 'today_yesterday_full':
            $from = new DateTime('yesterday 00:00:00', $tz);
            $to   = new DateTime('today 23:59:59', $tz);
            $label = 'Today + Yesterday Full';
            break;
        case 'last_2_hours':
            $from = (clone $now)->modify('-2 hours');
            $to   = clone $now;
            $label = 'Last 2 Hours';
            break;
        case 'last_4_hours':
            $from = (clone $now)->modify('-4 hours');
            $to   = clone $now;
            $label = 'Last 4 Hours';
            break;
        case 'last_8_hours':
            $from = (clone $now)->modify('-8 hours');
            $to   = clone $now;
            $label = 'Last 8 Hours';
            break;
        default:
            $from = new DateTime('today 00:00:00', $tz);
            $to   = new DateTime('today 23:59:59', $tz);
            $label = 'Today Full Day';
            break;
    }

    return [$from, $to, $label];
}

function db(): mysqli {
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user = defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : '');
    $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : '');
    $name = defined('DB_NAME') ? DB_NAME : '';

    $conn = @mysqli_connect($host, $user, $pass, $name);
    if (!$conn) {
        die('DB connect failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($conn, 'utf8mb4');
    return $conn;
}

$CACHE_DIR  = BASE_PATH . '/cache';
$CACHE_FILE = $CACHE_DIR . '/fb_leads_priority.csv';

if (!is_dir($CACHE_DIR)) {
    @mkdir($CACHE_DIR, 0777, true);
}

if (!is_file($CACHE_FILE) || (time() - filemtime($CACHE_FILE) > CACHE_TTL)) {
    $csvData = @file_get_contents(SHEET_PUBLISHED_CSV);
    if ($csvData !== false && trim($csvData) !== '') {
        @file_put_contents($CACHE_FILE, $csvData);
    }
}

$records = [];
$preset   = (string)($_GET['preset'] ?? 'today_full');
$fromInput = trim((string)($_GET['from_dt'] ?? ''));
$toInput   = trim((string)($_GET['to_dt'] ?? ''));

[$filterFrom, $filterTo, $filterLabel] = get_filter_range($preset, $fromInput, $toInput);

if (is_file($CACHE_FILE)) {
    $file = new SplFileObject($CACHE_FILE, 'r');
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

    $headers = [];

    foreach ($file as $index => $row) {
        if (!is_array($row) || $row === [null]) continue;

        if ($index === 0) {
            foreach ($row as $hh) {
                $norm = strtolower(trim((string)$hh));
                $norm2 = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', $norm));

                if ($norm2 === 'id') $headers[] = 'lead_id';
                elseif ($norm2 === 'created_time') $headers[] = 'created_at';
                elseif ($norm2 === 'createdat') $headers[] = 'created_at';
                elseif ($norm2 === 'ad_id') $headers[] = 'ad_id';
                elseif ($norm2 === 'ad_name') $headers[] = 'ad_name';
                elseif ($norm2 === 'adset_id') $headers[] = 'adset_id';
                elseif ($norm2 === 'adset_name') $headers[] = 'adset_name';
                elseif ($norm2 === 'campaign_id') $headers[] = 'campaign_id';
                elseif ($norm2 === 'campaign_name') $headers[] = 'campaign_name';
                elseif ($norm2 === 'campaignname') $headers[] = 'campaign_name';
                elseif ($norm2 === 'form_id') $headers[] = 'form_id';
                elseif ($norm2 === 'form_name') $headers[] = 'form_name';
                elseif ($norm2 === 'is_organic') $headers[] = 'is_organic';
                elseif ($norm2 === 'platform') $headers[] = 'platform';
                elseif ($norm2 === 'full_name') $headers[] = 'name';
                elseif ($norm2 === 'fullname') $headers[] = 'name';
                elseif ($norm2 === 'phone_number') $headers[] = 'phone';
                elseif ($norm2 === 'phonenumber') $headers[] = 'phone';
                elseif ($norm2 === 'mobile') $headers[] = 'phone';
                elseif ($norm2 === 'email') $headers[] = 'email';
                elseif ($norm2 === 'lead_status') $headers[] = 'lead_status';
                elseif (
                    $norm2 === 'how_many_travelers_are_planning_' ||
                    $norm2 === 'how_many_travelers_are_planning' ||
                    $norm2 === 'how_many_people_would_be_travelling' ||
                    $norm2 === 'howmanypeoplewouldbetravelling'
                ) {
                    $headers[] = 'pax';
                } else {
                    $headers[] = $norm2;
                }
            }
            continue;
        }

        if (count($row) < count($headers)) {
            $row = array_pad($row, count($headers), '');
        } elseif (count($row) > count($headers)) {
            $row = array_slice($row, 0, count($headers));
        }

        if (count($headers) === count($row)) {
            $data = array_combine($headers, $row);

            $leadDt = parse_lead_datetime((string)($data['created_at'] ?? ''));

            $data['phone'] = norm_phone($data['phone'] ?? '');
            $data['lead_source'] = 'facebook';
            $data['lead_source_label'] = 'Facebook';
            $data['_lead_dt'] = $leadDt;
            $data['_lead_ts'] = $leadDt ? $leadDt->getTimestamp() : 0;
            $data['_date_only'] = $leadDt ? $leadDt->format('Y-m-d') : '';
            $data['_display_dt'] = $leadDt ? $leadDt->format('Y-m-d h:i A') : '';
            $data['_phone_norm'] = $data['phone'];

            $records[] = $data;
        }
    }
}

$filtered = array_values(array_filter($records, function ($r) use ($filterFrom, $filterTo) {
    $ts = (int)($r['_lead_ts'] ?? 0);
    if ($ts <= 0) return false;

    if ($filterFrom instanceof DateTime && $ts < $filterFrom->getTimestamp()) return false;
    if ($filterTo instanceof DateTime && $ts > $filterTo->getTimestamp()) return false;
    return true;
}));

usort($filtered, function ($a, $b) {
    return ((int)($b['_lead_ts'] ?? 0)) <=> ((int)($a['_lead_ts'] ?? 0));
});

$rowKeys = [];
foreach ($filtered as $r) {
    $rawPhone     = norm_phone($r['phone'] ?? '');
    $email        = (string)($r['email'] ?? '');
    $campaignId   = (string)($r['campaign_id'] ?? '');
    $campaignName = trim((string)($r['campaign_name'] ?? ''));
    $createdRaw   = (string)($r['created_at'] ?? '');

    if ($campaignName === '') $campaignName = 'NO CAMPAIGN NAME';

    $rowKey = md5(
        strtolower(trim($rawPhone)) . '|' .
        strtolower(trim($createdRaw)) . '|' .
        strtolower(trim($email)) . '|' .
        strtolower(trim($campaignId)) . '|' .
        strtolower(trim($campaignName))
    );

    $rowKeys[] = $rowKey;
}

$existingAssignments = [];
if (!empty($rowKeys)) {
    $conn = db();

    $chunks = array_chunk(array_values(array_unique($rowKeys)), 500);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $types = str_repeat('s', count($chunk));

        $sql = "
            SELECT request_id, assign_to, userName, leadActualOwner
            FROM queryMaster
            WHERE request_id IN ($placeholders)
              AND leadSource = 'Facebook'
        ";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$chunk);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($res)) {
                $owner = trim((string)($row['assign_to'] ?? ''));
                if ($owner === '' || strtolower($owner) === 'na' || strtolower($owner) === 'unassigned') {
                    $owner = trim((string)($row['leadActualOwner'] ?? ''));
                }
                if ($owner === '' || strtolower($owner) === 'na' || strtolower($owner) === 'unassigned') {
                    $owner = trim((string)($row['userName'] ?? ''));
                }

                if ($owner !== '' && strtolower($owner) !== 'na' && strtolower($owner) !== 'unassigned') {
                    $existingAssignments[(string)$row['request_id']] = strtolower($owner);
                }
            }

            mysqli_stmt_close($stmt);
        }
    }

    mysqli_close($conn);
}

$campaignSummary = [];
foreach ($filtered as $r) {
    $campaignName = trim((string)($r['campaign_name'] ?? ''));
    if ($campaignName === '') $campaignName = 'NO CAMPAIGN NAME';

    if (!isset($campaignSummary[$campaignName])) {
        $campaignSummary[$campaignName] = [
            'campaign_name'   => $campaignName,
            'lead_count'      => 0,
            'assigned_count'  => 0,
            'fresh_count'     => 0,
            'pax_total'       => 0,
            'owners'          => []
        ];

        foreach ($owners as $o) {
            $campaignSummary[$campaignName]['owners'][$o] = 0;
        }
    }

    $rawPhone   = norm_phone($r['phone'] ?? '');
    $email      = (string)($r['email'] ?? '');
    $campaignId = (string)($r['campaign_id'] ?? '');
    $createdRaw = (string)($r['created_at'] ?? '');

    $rowKey = md5(
        strtolower(trim($rawPhone)) . '|' .
        strtolower(trim($createdRaw)) . '|' .
        strtolower(trim($email)) . '|' .
        strtolower(trim($campaignId)) . '|' .
        strtolower(trim($campaignName))
    );

    $campaignSummary[$campaignName]['lead_count']++;
    $campaignSummary[$campaignName]['pax_total'] += (int)($r['pax'] ?? 0);

    if (isset($existingAssignments[$rowKey])) {
        $campaignSummary[$campaignName]['assigned_count']++;
    }
}

$totalFreshLeads = 0;
foreach ($campaignSummary as $k => $row) {
    $campaignSummary[$k]['fresh_count'] = max(0, (int)$row['lead_count'] - (int)$row['assigned_count']);
    $totalFreshLeads += $campaignSummary[$k]['fresh_count'];
}

ksort($campaignSummary);

include BASE_PATH . '/includes/header.php';
?>

<style>
body { background: #f0f2f5; }
#campaignOwnerGrid td, #campaignOwnerGrid th { border: 1px solid #d9dee7 !important; font-weight: bold; font-size: 14px; color: black; }
.main-card, .topbar-card, .grid-card { border: none; border-radius: 18px; box-shadow: 0 14px 34px rgba(0,0,0,.08); background: #fff; }
.table thead th { background: #f8f9fa; color: #6c757d; font-size: .72rem; text-transform: uppercase; font-weight: 900; border-bottom: 2px solid #edf2f7; vertical-align: middle; white-space: nowrap; }
.assign-select { min-width: 160px; border-radius: 10px !important; font-weight: 700 !important; }
.assign-select:disabled { background: #e9f7ef !important; color: #198754 !important; border-color: #b7e4c7 !important; opacity: 1 !important; cursor: not-allowed; }
.pick-col { min-width: 58px; max-width: 58px; text-align: center; }
.pick-head { position: sticky; left: 0; z-index: 10 !important; background: #f8f9fa !important; }
.pick-cell { position: sticky; left: 0; z-index: 5; background: var(--row-bg, #fff) !important; text-align: center; }
.sticky-first { position: sticky; left: 58px; background: var(--row-bg, #fff) !important; z-index: 4; }
.sticky-second { position: sticky; left: 378px; background: var(--row-bg, #fff) !important; z-index: 4; }
.campaign-col { min-width: 320px; max-width: 320px; }
.meta-col { min-width: 140px; max-width: 140px; }
.owner-head { min-width: 92px; text-align: center; }
.small-note { font-size: .85rem; color: #6c757d; }
.badge-soft { background: #eef3ff; color: #234; padding: 8px 12px; border-radius: 999px; font-weight: 700; }
.row-saved, .row-assigned { background: #f3fff7 !important; }
.row-saved td, .row-assigned td { border-color: #d8f3dc !important; }
.saved-badge { display: inline-block; margin-left: 6px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; background: #198754; color: #fff; }
.assigned-owner-note { display: inline-block; margin-top: 4px; font-size: 11px; font-weight: 700; color: #198754; }
#campaignOwnerGrid { border-collapse: separate; border-spacing: 0; }
#campaignOwnerGrid td, #campaignOwnerGrid th { border: 1px solid #d9dee7 !important; }
#campaignOwnerGrid thead th { position: sticky; top: 0; z-index: 8; }
#campaignOwnerGrid tfoot th { position: sticky; bottom: 0; background: #eef2f7 !important; z-index: 7; border-top: 2px solid #cfd8e3 !important; font-size: 13px; font-weight: 800 !important; color: #111827 !important; }
.grid-total-row th { background: #eef2f7 !important; transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
.grid-total-row .pick-head, .grid-total-row .sticky-first, .grid-total-row .sticky-second { background: #e5ebf3 !important; }
.grid-total-match { background: #eafaf1 !important; color: #166534 !important; border-color: #b7e4c7 !important; }
.grid-total-over { background: #fff4f4 !important; color: #b42318 !important; border-color: #ffb3b3 !important; }
.grid-total-neutral { background: #eef2f7 !important; color: #111827 !important; border-color: #cfd8e3 !important; }
.grid-cell-wrap { padding: 0 !important; }
.grid-input { width: 100%; min-width: 70px; max-width: 90px; height: 38px; padding: 4px 6px; border: none !important; border-radius: 0 !important; box-shadow: none !important; outline: none !important; text-align: center; font-size: 14px; font-weight: 700; background: transparent !important; color: #212529; }
.grid-input:hover { background: #f7fbff !important; }
.grid-input:focus { background: #fff8db !important; box-shadow: inset 0 0 0 2px #2f80ed !important; }
.grid-input.cell-filled { color: #0d47a1; }
.row-complete { background: #eafaf1 !important; }
.row-complete td { border-color: #b7e4c7 !important; }
.row-overflow { background: #fff4f4 !important; }
.row-overflow td { border-color: #ffb3b3 !important; }
.fresh-badge { min-width: 44px; display: inline-block; }
.tick-mark { margin-left: 6px; font-size: 16px; font-weight: 900; color: #198754; }
.cross-mark { margin-left: 6px; font-size: 16px; font-weight: 900; color: #dc3545; }
.grid-stat-pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: #f7f9fc; border: 1px solid #e4e9f2; font-weight: 800; color: #213547; }
.grid-stat-pill strong { color: #0d6efd; }
.grid-legend { display: flex; flex-wrap: wrap; gap: 10px; font-size: 12px; }
.grid-legend span { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #f8f9fb; border: 1px solid #eceff5; }
.legend-box { width: 12px; height: 12px; border-radius: 2px; display: inline-block; }
.legend-complete { background: #eafaf1; border: 1px solid #b7e4c7; }
.legend-focus { background: #fff8db; border: 1px solid #f0d36e; }
.legend-over { background: #fff4f4; border: 1px solid #ffb3b3; }
.grid-input::-webkit-outer-spin-button, .grid-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.grid-input[type=number] { -moz-appearance: textfield; }
#campaignOwnerGrid tbody tr td.text-center, #campaignOwnerGrid tbody tr th.text-center { vertical-align: middle; }
.js-grid-row-check, .js-grid-col-check { transform: scale(1.08); cursor: pointer; }
.grid-row-picked td { background: var(--row-bg, #eef6ff) !important; }
.grid-row-picked .pick-cell, .grid-row-picked .sticky-first, .grid-row-picked .sticky-second { background: var(--row-bg, #eef6ff) !important; }
.owner-col-head.col-picked { outline: 2px solid #2563eb; outline-offset: -2px; }
.col-hidden { display: none !important; }
#campaignOwnerGrid tbody tr { background: var(--row-bg, #ffffff); }
#campaignOwnerGrid tbody tr .pick-cell, #campaignOwnerGrid tbody tr .sticky-first, #campaignOwnerGrid tbody tr .sticky-second { background: var(--row-bg, #ffffff) !important; }
.owner-col-head { background: var(--col-bg, #f8f9fa) !important; }
#campaignOwnerGrid tbody td.col-colored { background: var(--col-bg, #f3f4f6) !important; }
#campaignOwnerGrid tbody tr.row-complete td.col-colored { filter: saturate(0.9) brightness(1.02); }
#campaignOwnerGrid tbody tr.row-overflow td.col-colored { filter: saturate(0.9) brightness(0.98); }
.js-owner-col-total { background: var(--col-bg, #f3f4f6) !important; }
#gridScreenshotCloneWrap { position: fixed; left: -99999px; top: -99999px; background: #ffffff; padding: 16px; z-index: -1; }
#gridScreenshotCloneWrap table { border-collapse: collapse; width: auto; background: #fff; }
#gridScreenshotCloneWrap th, #gridScreenshotCloneWrap td { border: 1px solid #d9dee7; padding: 8px 10px; white-space: nowrap; text-align: center; font-size: 12px; font-weight: 700; color: #111827; }
#gridScreenshotCloneWrap th { background: #f8f9fa; color: #6c757d; font-weight: 800; text-transform: uppercase; }
#gridScreenshotCloneWrap .campaign-name-cell { text-align: left; font-weight: 700; min-width: 280px; }
#gridScreenshotCloneWrap .fresh-cell { min-width: 100px; }
#gridScreenshotCloneWrap .total-row td { background: #f3f4f6; font-weight: 800; }
</style>

<div id="page-wrapper" class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-0">Facebook Leads Direct LeadActualOwner Assign</h2>
            <div class="text-muted mt-1">Use exact date and time range for multiple assignment cycles in a day.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge-soft">Visible Leads: <?= (int)count($filtered) ?></span>
            <span class="badge-soft">Campaigns: <?= (int)count($campaignSummary) ?></span>
            <span class="badge-soft"><?= h($filterLabel) ?></span>
        </div>
    </div>

    <div class="card topbar-card p-3 mb-3">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">Quick Preset</label>
                <select name="preset" class="form-select" id="presetSelect">
                    <option value="today_full" <?= $preset === 'today_full' ? 'selected' : '' ?>>Today Full Day</option>
                    <option value="today_0930_1730" <?= $preset === 'today_0930_1730' ? 'selected' : '' ?>>Today 09:30 AM to 05:30 PM</option>
                    <option value="yesterday_full" <?= $preset === 'yesterday_full' ? 'selected' : '' ?>>Yesterday Full Day</option>
                    <option value="today_yesterday_full" <?= $preset === 'today_yesterday_full' ? 'selected' : '' ?>>Today + Yesterday Full</option>
                    <option value="last_2_hours" <?= $preset === 'last_2_hours' ? 'selected' : '' ?>>Last 2 Hours</option>
                    <option value="last_4_hours" <?= $preset === 'last_4_hours' ? 'selected' : '' ?>>Last 4 Hours</option>
                    <option value="last_8_hours" <?= $preset === 'last_8_hours' ? 'selected' : '' ?>>Last 8 Hours</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">From Date & Time</label>
                <input type="datetime-local" name="from_dt" id="from_dt" class="form-control" value="<?= h($fromInput) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">To Date & Time</label>
                <input type="datetime-local" name="to_dt" id="to_dt" class="form-control" value="<?= h($toInput) ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Load Leads</button>
                <a href="?preset=today_full" class="btn btn-outline-secondary">Reset</a>
            </div>

            <div class="col-12">
                <div class="small-note">
                    Better workflow for 3â€“4 assignments daily: use exact date-time ranges such as 09:30â€“12:30, 12:30â€“03:30, 03:30â€“05:30. Manual From/To overrides preset.
                </div>
            </div>
        </form>
    </div>

    <div class="card grid-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Campaign-wise Owner Grid</h5>
                <div class="small-note">
                    Enter counts owner-wise against each Campaign Name. Already assigned leads are excluded from fresh grid assignment.
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" id="fillZeroBtn" class="btn btn-outline-secondary btn-sm">Fill Blank as 0</button>
                <button type="button" id="btnApplyGrid" class="btn btn-warning btn-sm">Apply Grid Assignment</button>
                <button type="button" id="btnExportGridCsv" class="btn btn-success btn-sm">Export CSV</button>
                <button type="button" id="btnExportGridExcel" class="btn btn-success btn-sm">Export Excel</button>
                <button type="button" id="btnGridScreenshot" class="btn btn-info btn-sm">Download Screenshot</button>
                <button type="button" id="btnSelectAllGridRows" class="btn btn-outline-primary btn-sm">Select All Rows</button>
                <button type="button" id="btnClearAllGridRows" class="btn btn-outline-dark btn-sm">Clear Rows</button>
                <button type="button" id="btnSelectAllGridCols" class="btn btn-outline-primary btn-sm">Select All Columns</button>
                <button type="button" id="btnClearAllGridCols" class="btn btn-outline-dark btn-sm">Clear Columns</button>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="grid-stat-pill">Total Fresh Leads: <strong id="totalFreshLeads"><?= (int)$totalFreshLeads ?></strong></span>
            <span class="grid-stat-pill">Planned in Grid: <strong id="totalGridPlanned">0</strong></span>
            <span class="grid-stat-pill">Remaining: <strong id="totalGridRemaining"><?= (int)$totalFreshLeads ?></strong></span>
        </div>

        <div class="mb-3">
            <div class="grid-legend">
                <span><i class="legend-box legend-complete"></i> Fresh count matched</span>
                <span><i class="legend-box legend-focus"></i> Active cell</span>
                <span><i class="legend-box legend-over"></i> Over assigned</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="campaignOwnerGrid">
                <thead>
                    <tr>
                        <th class="pick-col pick-head">Pick</th>
                        <th class="sticky-first campaign-col">Campaign Name</th>
                        <th class="sticky-second meta-col text-center">Fresh Leads</th>
                        <?php foreach ($owners as $idx => $o): ?>
                            <?php $colBg = $ownerPalette[$o] ?? '#f3f4f6'; ?>
                            <th
                                class="owner-head text-center owner-col-head"
                                data-owner-col-index="<?= (int)$idx ?>"
                                data-owner-name="<?= h($o) ?>"
                                data-col-bg="<?= h($colBg) ?>"
                                style="--col-bg: <?= h($colBg) ?>;"
                            >
                                <div><?= h(ucwords($o)) ?></div>
                                <div class="mt-1">
                                    <input
                                        type="checkbox"
                                        class="form-check-input js-grid-col-check"
                                        data-owner-col-index="<?= (int)$idx ?>"
                                        data-owner-name="<?= h($o) ?>"
                                        checked
                                    >
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($campaignSummary)): ?>
                        <tr>
                            <td colspan="<?= 3 + count($owners) ?>" class="text-center py-4 text-muted">No campaign names found for selected range.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $campaignNamesList = array_keys($campaignSummary);
                        foreach ($campaignSummary as $campaignName => $row):
                            $freshCount = (int)$row['fresh_count'];
                            $rowIndex = array_search($campaignName, $campaignNamesList, true);
                            if ($rowIndex === false) $rowIndex = 0;
                            $rowBg = $rowPalette[$rowIndex % count($rowPalette)];
                        ?>
                            <tr
                                data-campaign-name="<?= h($campaignName) ?>"
                                data-visible-leads="<?= (int)$freshCount ?>"
                                data-grid-total="0"
                                data-row-bg="<?= h($rowBg) ?>"
                                style="--row-bg: <?= h($rowBg) ?>;"
                            >
                                <td class="pick-cell">
                                    <input
                                        type="checkbox"
                                        class="form-check-input js-grid-row-check"
                                        data-campaign-name="<?= h($campaignName) ?>"
                                        checked
                                    >
                                </td>

                                <td class="sticky-first campaign-col fw-bold">
                                    <?= h($campaignName) ?>
                                    <?php if ((int)$row['assigned_count'] > 0): ?>
                                        <div class="small-note mt-1">
                                            Assigned: <?= (int)$row['assigned_count'] ?> | Fresh: <?= (int)$freshCount ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small-note mt-1">
                                            Fresh: <?= (int)$freshCount ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="sticky-second meta-col text-center">
                                    <span class="badge bg-primary fresh-badge js-fresh-badge"><?= (int)$freshCount ?></span>
                                    <span class="js-row-mark"></span>
                                </td>

                                <?php foreach ($owners as $colIndex => $o): ?>
                                    <?php $colBg = $ownerPalette[$o] ?? '#f3f4f6'; ?>
                                    <td class="text-center grid-cell-wrap col-colored" style="--col-bg: <?= h($colBg) ?>;">
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            inputmode="numeric"
                                            value="0"
                                            class="form-control form-control-sm grid-input js-grid-owner"
                                            data-campaign-name="<?= h($campaignName) ?>"
                                            data-owner="<?= h($o) ?>"
                                            data-row-index="<?= h((string)$rowIndex) ?>"
                                            data-col-index="<?= h((string)$colIndex) ?>"
                                            data-owner-col-index="<?= h((string)$colIndex) ?>"
                                            data-col-bg="<?= h($colBg) ?>"
                                            style="--col-bg: <?= h($colBg) ?>;"
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

                <tfoot id="campaignOwnerGridFoot">
                    <tr class="grid-total-row">
                        <th class="pick-head text-center">Total</th>
                        <th class="sticky-first campaign-col">All Campaigns</th>
                        <th class="sticky-second meta-col text-center grid-total-neutral" id="gridFootFreshTotal"><?= (int)$totalFreshLeads ?></th>
                        <?php foreach ($owners as $idx => $o): ?>
                            <?php $colBg = $ownerPalette[$o] ?? '#f3f4f6'; ?>
                            <th
                                class="text-center js-owner-col-total grid-total-neutral"
                                data-owner-col-index="<?= (int)$idx ?>"
                                data-col-bg="<?= h($colBg) ?>"
                                style="--col-bg: <?= h($colBg) ?>;"
                            >0</th>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="card topbar-card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div id="assignStatus" class="text-muted fw-bold">Ready</div>
            <button type="button" id="btnSaveSubmit" class="btn btn-primary">
                <i class="fa fa-save"></i> Save & Submit
            </button>
        </div>
    </div>

    <div class="card main-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="leadsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Created</th>
                        <th>Campaign Name</th>
                        <th>Destination / Ad Name</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Campaign ID</th>
                        <th>Platform</th>
                        <th>Pax</th>
                        <th>Lead Source</th>
                        <th>LeadActualOwner</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filtered)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">No leads found for selected time range.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filtered as $i => $r): ?>
                            <?php
                            $rawPhone     = norm_phone($r['phone'] ?? '');
                            $created      = (string)($r['_display_dt'] ?? '');
                            $email        = (string)($r['email'] ?? '');
                            $campaignId   = (string)($r['campaign_id'] ?? '');
                            $campaignName = trim((string)($r['campaign_name'] ?? ''));
                            $adName       = (string)($r['ad_name'] ?? '');
                            $platform     = (string)($r['platform'] ?? '');
                            $paxVal       = (string)($r['pax'] ?? '0');
                            $createdRaw   = (string)($r['created_at'] ?? '');

                            if ($campaignName === '') $campaignName = 'NO CAMPAIGN NAME';

                            $rowKey = md5(
                                strtolower(trim($rawPhone)) . '|' .
                                strtolower(trim($createdRaw)) . '|' .
                                strtolower(trim($email)) . '|' .
                                strtolower(trim($campaignId)) . '|' .
                                strtolower(trim($campaignName))
                            );

                            $assignedOwner = $existingAssignments[$rowKey] ?? '';
                            $isAssigned = $assignedOwner !== '';
                            ?>
                            <tr
                                data-campaign-name="<?= h($campaignName) ?>"
                                data-saved="<?= $isAssigned ? '1' : '0' ?>"
                                class="<?= $isAssigned ? 'row-assigned' : '' ?>"
                            >
                                <td><?= (int)($i + 1) ?></td>
                                <td class="c-created"><?= h($created !== '' ? $created : 'â€”') ?></td>
                                <td class="c-campaign">
                                    <?= h($campaignName) ?>
                                    <?php if ($isAssigned): ?>
                                        <span class="saved-badge">Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="c-destination"><?= h($adName !== '' ? $adName : 'â€”') ?></td>
                                <td class="c-name"><?= h($r['name'] ?? 'â€”') ?></td>
                                <td class="c-phone"><?= h($rawPhone !== '' ? $rawPhone : 'â€”') ?></td>
                                <td class="c-email"><?= h($email !== '' ? $email : 'â€”') ?></td>
                                <td class="c-campaignid"><?= h($campaignId !== '' ? $campaignId : 'â€”') ?></td>
                                <td class="c-platform"><?= h($platform !== '' ? $platform : 'â€”') ?></td>
                                <td class="c-pax text-center"><?= h($paxVal !== '' ? $paxVal : '0') ?></td>
                                <td class="c-source">facebook</td>
                                <td>
                                    <select class="form-select form-select-sm assign-select js-owner"
                                        data-rowkey="<?= h($rowKey) ?>"
                                        data-phone10="<?= h($rawPhone) ?>"
                                        data-phoneraw="<?= h($rawPhone) ?>"
                                        data-created="<?= h($createdRaw) ?>"
                                        data-destination="<?= h($adName) ?>"
                                        data-name="<?= h($r['name'] ?? '') ?>"
                                        data-email="<?= h($email) ?>"
                                        data-campaign="<?= h($campaignName) ?>"
                                        data-campaign_name="<?= h($campaignName) ?>"
                                        data-campaignid="<?= h($campaignId) ?>"
                                        data-leadsource="facebook"
                                        data-platform="<?= h($platform) ?>"
                                        data-pax="<?= h($paxVal) ?>"
                                        <?= $isAssigned ? 'disabled' : '' ?>>
                                        <option value="">Select</option>
                                        <?php foreach ($owners as $o): ?>
                                            <option value="<?= h($o) ?>" <?= $assignedOwner === $o ? 'selected' : '' ?>>
                                                <?= h(ucwords($o)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($isAssigned): ?>
                                        <div class="assigned-owner-note">Already assigned to <?= h(ucwords($assignedOwner)) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
(function () {
    const btnApplyGrid   = document.getElementById('btnApplyGrid');
    const btnSaveSubmit  = document.getElementById('btnSaveSubmit');
    const assignStatus   = document.getElementById('assignStatus');
    const fillZeroBtn    = document.getElementById('fillZeroBtn');
    const presetSelect   = document.getElementById('presetSelect');
    const fromDt         = document.getElementById('from_dt');
    const toDt           = document.getElementById('to_dt');
    const totalFreshEl   = document.getElementById('totalFreshLeads');
    const totalPlannedEl = document.getElementById('totalGridPlanned');
    const totalRemainEl  = document.getElementById('totalGridRemaining');
    const gridFootFreshTotal = document.getElementById('gridFootFreshTotal');

    const btnExportGridCsv   = document.getElementById('btnExportGridCsv');
    const btnExportGridExcel = document.getElementById('btnExportGridExcel');
    const btnGridScreenshot  = document.getElementById('btnGridScreenshot');
    const btnSelectAllGridRows = document.getElementById('btnSelectAllGridRows');
    const btnClearAllGridRows  = document.getElementById('btnClearAllGridRows');
    const btnSelectAllGridCols = document.getElementById('btnSelectAllGridCols');
    const btnClearAllGridCols  = document.getElementById('btnClearAllGridCols');

    function qsAll(sel, ctx) {
        return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    }

    function intVal(v) {
        const n = parseInt(v || '0', 10);
        return isNaN(n) || n < 0 ? 0 : n;
    }

    function setTotalCellState(el, freshTotal, currentTotal) {
        if (!el) return;
        el.classList.remove('grid-total-neutral', 'grid-total-match', 'grid-total-over');
        if (currentTotal === freshTotal) el.classList.add('grid-total-match');
        else if (currentTotal > freshTotal) el.classList.add('grid-total-over');
        else el.classList.add('grid-total-neutral');
    }

    function buildPayloadFromSelect(sel) {
        const ds = sel.dataset;
        return {
            row_key: ds.rowkey || '',
            phone10: ds.phone10 || '',
            phone_raw: ds.phoneraw || '',
            created_ist: ds.created || '',
            destination: ds.destination || '',
            name: ds.name || '',
            email: ds.email || '',
            campaign: ds.campaign || '',
            campaign_name: ds.campaign_name || ds.campaign || '',
            campaign_id: ds.campaignid || '',
            lead_source: 'facebook',
            lead_source_label: 'Facebook',
            platform: ds.platform || '',
            pax: ds.pax || '',
            assigned_to: sel.value || ''
        };
    }

    function markRowSaved(sel, modeText) {
        const tr = sel.closest('tr');
        if (!tr) return;

        tr.dataset.saved = '1';
        tr.classList.add('row-saved');
        tr.classList.add('row-assigned');
        sel.disabled = true;

        const cellCampaign = tr.querySelector('.c-campaign');
        if (cellCampaign && !cellCampaign.querySelector('.saved-badge')) {
            const badge = document.createElement('span');
            badge.className = 'saved-badge';
            badge.textContent = modeText || 'Assigned';
            cellCampaign.appendChild(badge);
        }

        let note = tr.querySelector('.assigned-owner-note');
        if (!note) {
            note = document.createElement('div');
            note.className = 'assigned-owner-note';
            sel.parentNode.appendChild(note);
        }

        const val = sel.value ? sel.value.charAt(0).toUpperCase() + sel.value.slice(1) : '';
        note.textContent = 'Already assigned to ' + val;
    }

    function collectGridMap() {
        const map = {};
        qsAll('#campaignOwnerGrid tbody tr[data-campaign-name]').forEach(tr => {
            const campaignName = (tr.getAttribute('data-campaign-name') || '').trim();
            if (!campaignName) return;

            map[campaignName] = [];
            qsAll('.js-grid-owner', tr).forEach(inp => {
                const owner = (inp.getAttribute('data-owner') || '').trim();
                const safeCount = intVal(inp.value);
                for (let i = 0; i < safeCount; i++) {
                    map[campaignName].push(owner);
                }
            });
        });
        return map;
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
        return value.replace(/["\\]/g, '\\$&');
    }

    function validateGridAgainstLeads(gridMap) {
        const errors = [];

        qsAll('#campaignOwnerGrid tbody tr[data-campaign-name]').forEach(tr => {
            const campaignName = (tr.getAttribute('data-campaign-name') || '').trim();
            const unsavedRows = qsAll('#leadsTable tbody tr[data-campaign-name="' + cssEscape(campaignName) + '"]')
                .filter(row => row.dataset.saved !== '1').length;
            const filled = (gridMap[campaignName] || []).length;

            if (filled > unsavedRows) {
                errors.push(campaignName + ' => assigned ' + filled + ' but fresh visible leads are only ' + unsavedRows);
            }
        });

        return errors;
    }

    function clearUnSavedLeadOwners() {
        qsAll('.js-owner').forEach(sel => {
            const tr = sel.closest('tr');
            if (!tr || tr.dataset.saved === '1' || sel.disabled) return;
            sel.value = '';
        });
    }

    async function saveOne(payload) {
        const resp = await fetch('assign_lead_fb_real.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        const text = await resp.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error('Non-JSON from server: ' + text.slice(0, 250));
        }

        if (!resp.ok || !data || !data.ok) {
            throw new Error(data && data.error ? data.error : 'Save failed');
        }

        return data;
    }

    function getGridRows() {
        return qsAll('#campaignOwnerGrid tbody tr[data-campaign-name]');
    }

    function getSelectedGridRowsStrict() {
        return getGridRows().filter(tr => {
            const cb = tr.querySelector('.js-grid-row-check');
            return cb && cb.checked;
        });
    }

    function getSelectedGridRows() {
        const rows = getSelectedGridRowsStrict();
        return rows.length ? rows : getGridRows();
    }

    function getSelectedColumnIndexesStrict() {
        return qsAll('.js-grid-col-check')
            .filter(cb => cb.checked)
            .map(cb => intVal(cb.getAttribute('data-owner-col-index')))
            .sort((a, b) => a - b);
    }

    function getSelectedColumnIndexes() {
        const cols = getSelectedColumnIndexesStrict();
        if (cols.length) return cols;

        const firstHead = document.querySelector('.owner-col-head[data-owner-col-index]');
        return firstHead ? [intVal(firstHead.getAttribute('data-owner-col-index'))] : [];
    }

    function getSelectedColumnHeaders() {
        const selectedCols = getSelectedColumnIndexes();
        return selectedCols.map(idx => {
            const th = document.querySelector('.owner-col-head[data-owner-col-index="' + idx + '"]');
            if (!th) return 'Owner ' + idx;
            const titleDiv = th.querySelector('div');
            return titleDiv ? titleDiv.textContent.trim() : ('Owner ' + idx);
        });
    }

    function updatePickedRowStyle() {
        getGridRows().forEach(tr => {
            const cb = tr.querySelector('.js-grid-row-check');
            if (cb && cb.checked) tr.classList.add('grid-row-picked');
            else tr.classList.remove('grid-row-picked');
        });
    }

    function updatePickedColumnStyle() {
        const selectedIndexes = getSelectedColumnIndexes();
        qsAll('.owner-col-head').forEach(th => {
            const idx = intVal(th.getAttribute('data-owner-col-index'));
            if (selectedIndexes.indexOf(idx) !== -1) th.classList.add('col-picked');
            else th.classList.remove('col-picked');
        });
    }

    function applyColumnCellColors() {
        qsAll('#campaignOwnerGrid tbody tr').forEach(tr => {
            qsAll('.js-grid-owner', tr).forEach(inp => {
                const td = inp.closest('td');
                if (!td) return;
                td.classList.add('col-colored');
                const colBg = inp.getAttribute('data-col-bg') || '#f3f4f6';
                td.style.setProperty('--col-bg', colBg);
            });
        });

        qsAll('.js-owner-col-total').forEach(th => {
            const colBg = th.getAttribute('data-col-bg') || '#f3f4f6';
            th.style.setProperty('--col-bg', colBg);
        });

        qsAll('.owner-col-head').forEach(th => {
            const colBg = th.getAttribute('data-col-bg') || '#f3f4f6';
            th.style.setProperty('--col-bg', colBg);
        });
    }

    function applyColumnVisibility() {
        const selectedIndexes = getSelectedColumnIndexes();

        qsAll('.owner-col-head').forEach(th => {
            const idx = intVal(th.getAttribute('data-owner-col-index'));
            th.classList.toggle('col-hidden', selectedIndexes.indexOf(idx) === -1);
        });

        qsAll('.js-grid-owner').forEach(inp => {
            const idx = intVal(inp.getAttribute('data-owner-col-index'));
            const td = inp.closest('td');
            if (td) td.classList.toggle('col-hidden', selectedIndexes.indexOf(idx) === -1);
        });

        qsAll('.js-owner-col-total').forEach(th => {
            const idx = intVal(th.getAttribute('data-owner-col-index'));
            th.classList.toggle('col-hidden', selectedIndexes.indexOf(idx) === -1);
        });

        updatePickedColumnStyle();
    }

    function updateRowStatus(tr) {
        if (!tr) return;

        const fresh = intVal(tr.getAttribute('data-visible-leads'));
        let total = 0;

        qsAll('.js-grid-owner', tr).forEach(inp => {
            const v = intVal(inp.value);
            total += v;
            if (v > 0) inp.classList.add('cell-filled');
            else inp.classList.remove('cell-filled');
        });

        tr.setAttribute('data-grid-total', String(total));

        const badge = tr.querySelector('.js-fresh-badge');
        const markWrap = tr.querySelector('.js-row-mark');

        tr.classList.remove('row-complete', 'row-overflow');
        if (badge) {
            badge.classList.remove('bg-primary', 'bg-success', 'bg-danger');
            badge.classList.add('bg-primary');
        }
        if (markWrap) markWrap.innerHTML = '';

        if (fresh > 0 && total === fresh) {
            tr.classList.add('row-complete');
            if (badge) {
                badge.classList.remove('bg-primary');
                badge.classList.add('bg-success');
            }
            if (markWrap) markWrap.innerHTML = '<span class="tick-mark">âœ”</span>';
        } else if (total > fresh && fresh >= 0) {
            tr.classList.add('row-overflow');
            if (badge) {
                badge.classList.remove('bg-primary');
                badge.classList.add('bg-danger');
            }
            if (markWrap) markWrap.innerHTML = '<span class="cross-mark">âœ–</span>';
        }

        updateGridTotals();
        updateFrontendTotalsRow();
    }

    function updateGridTotals() {
        let totalPlanned = 0;
        getGridRows().forEach(tr => {
            totalPlanned += intVal(tr.getAttribute('data-grid-total'));
        });

        const totalFresh = intVal(totalFreshEl ? totalFreshEl.textContent : '0');
        const remaining = totalFresh - totalPlanned;

        if (totalPlannedEl) totalPlannedEl.textContent = totalPlanned;
        if (totalRemainEl) totalRemainEl.textContent = remaining;
    }

    function updateFrontendTotalsRow() {
        const rows = getGridRows();
        const ownerTotalsEls = qsAll('.js-owner-col-total');

        let freshTotal = 0;
        const ownerTotals = new Array(ownerTotalsEls.length).fill(0);

        rows.forEach(tr => {
            freshTotal += intVal(tr.getAttribute('data-visible-leads'));
            qsAll('.js-grid-owner', tr).forEach(inp => {
                const idx = intVal(inp.getAttribute('data-owner-col-index'));
                if (typeof ownerTotals[idx] !== 'undefined') ownerTotals[idx] += intVal(inp.value);
            });
        });

        if (gridFootFreshTotal) gridFootFreshTotal.textContent = freshTotal;

        ownerTotalsEls.forEach((el) => {
            const idx = intVal(el.getAttribute('data-owner-col-index'));
            const currentTotal = ownerTotals[idx] || 0;
            el.textContent = currentTotal;
            setTotalCellState(el, freshTotal, currentTotal);
        });

        setTotalCellState(gridFootFreshTotal, freshTotal, freshTotal);
    }

    function moveCell(current, direction) {
        const row = intVal(current.dataset.rowIndex);
        const col = intVal(current.dataset.colIndex);

        let targetRow = row;
        let targetCol = col;

        if (direction === 'left') targetCol--;
        if (direction === 'right') targetCol++;
        if (direction === 'up') targetRow--;
        if (direction === 'down') targetRow++;

        const target = document.querySelector(
            '.js-grid-owner[data-row-index="' + targetRow + '"][data-col-index="' + targetCol + '"]'
        );

        if (target) {
            target.focus();
            target.select();
        }
    }

    function bindGridInput(inp) {
        inp.addEventListener('input', function () {
            const clean = this.value.replace(/[^\d]/g, '');
            this.value = clean === '' ? '0' : String(intVal(clean));
            updateRowStatus(this.closest('tr'));
        });

        inp.addEventListener('focus', function () { this.select(); });
        inp.addEventListener('dblclick', function () { this.select(); });

        inp.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); moveCell(this, 'left'); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); moveCell(this, 'right'); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveCell(this, 'up'); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); moveCell(this, 'down'); }
            else if (e.key === 'Enter') { e.preventDefault(); moveCell(this, 'down'); }
            else if (e.key === 'Tab') {}
            else if (!/[0-9]|Backspace|Delete|Home|End/.test(e.key) && e.key.length === 1) {
                e.preventDefault();
            }
        });

        updateRowStatus(inp.closest('tr'));
    }

    function extractRowValues(tr, selectedCols) {
        const valueMap = {};
        qsAll('.js-grid-owner', tr).forEach(inp => {
            const idx = intVal(inp.getAttribute('data-owner-col-index'));
            valueMap[idx] = intVal(inp.value);
        });

        return selectedCols.map(idx => {
            return Object.prototype.hasOwnProperty.call(valueMap, idx) ? valueMap[idx] : 0;
        });
    }

    function getExportContext() {
        let rows = getSelectedGridRowsStrict();
        if (!rows.length) rows = getGridRows();

        let selectedCols = getSelectedColumnIndexesStrict();
        if (!selectedCols.length) {
            const firstHead = document.querySelector('.owner-col-head[data-owner-col-index]');
            selectedCols = firstHead ? [intVal(firstHead.getAttribute('data-owner-col-index'))] : [];
        }

        const headers = ['Campaign Name', 'Fresh Leads'].concat(
            selectedCols.map(idx => {
                const th = document.querySelector('.owner-col-head[data-owner-col-index="' + idx + '"]');
                if (!th) return 'Owner ' + idx;
                const titleDiv = th.querySelector('div');
                return titleDiv ? titleDiv.textContent.trim() : ('Owner ' + idx);
            })
        );

        const bodyRows = [];
        let freshTotal = 0;
        const ownerTotals = new Array(selectedCols.length).fill(0);

        rows.forEach(tr => {
            const campaignName = (tr.getAttribute('data-campaign-name') || '').trim();
            const fresh = intVal(tr.getAttribute('data-visible-leads'));
            const ownerVals = extractRowValues(tr, selectedCols);

            freshTotal += fresh;
            ownerVals.forEach((val, i) => { ownerTotals[i] += val; });

            bodyRows.push([campaignName, fresh].concat(ownerVals));
        });

        const totalsRow = ['Total', freshTotal].concat(ownerTotals);

        return { rows, selectedCols, headers, bodyRows, totalsRow };
    }

    function csvEscape(val) {
        const s = String(val ?? '');
        if (/[",\n]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
        return s;
    }

    function downloadCsv(filename, rows) {
        const csv = rows.map(row => row.map(csvEscape).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();

        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function buildExcelHTML(ctx) {
        if (!ctx || !ctx.bodyRows.length) return '';

        let html = '<table border="1" style="border-collapse:collapse;">';

        html += '<tr>';
        ctx.headers.forEach((h, idx) => {
            let style = 'font-weight:bold;background:#f8f9fa;';
            if (idx >= 2) {
                const realIdx = ctx.selectedCols[idx - 2];
                const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                if (head) style = 'font-weight:bold;background:' + (head.getAttribute('data-col-bg') || '#f3f4f6') + ';';
            }
            html += '<td style="' + style + '">' + String(h) + '</td>';
        });
        html += '</tr>';

        ctx.rows.forEach((tr, rowIndex) => {
            const rowBg = tr.getAttribute('data-row-bg') || '#ffffff';
            const rowData = ctx.bodyRows[rowIndex];

            html += '<tr>';
            rowData.forEach((val, idx) => {
                let style = 'text-align:center;';
                if (idx === 0) {
                    style += 'background:' + rowBg + ';font-weight:bold;text-align:left;';
                } else if (idx === 1) {
                    style += 'background:' + rowBg + ';font-weight:bold;';
                } else {
                    const realIdx = ctx.selectedCols[idx - 2];
                    const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                    style += 'background:' + (head ? (head.getAttribute('data-col-bg') || '#f3f4f6') : '#f3f4f6') + ';';
                }
                html += '<td style="' + style + '">' + String(val) + '</td>';
            });
            html += '</tr>';
        });

        html += '<tr>';
        ctx.totalsRow.forEach((val, idx) => {
            let style = 'background:#f3f4f6;font-weight:bold;';
            if (idx >= 2) {
                const realIdx = ctx.selectedCols[idx - 2];
                const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                style = 'background:' + (head ? (head.getAttribute('data-col-bg') || '#f3f4f6') : '#f3f4f6') + ';font-weight:bold;';
            }
            html += '<td style="' + style + '">' + String(val) + '</td>';
        });
        html += '</tr>';

        html += '</table>';
        return html;
    }

    function downloadExcel(filename) {
        const ctx = getExportContext();
        const html = buildExcelHTML(ctx);
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();

        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    function buildScreenshotTable(ctx) {
        if (!ctx || !ctx.bodyRows.length) return null;

        const wrap = document.createElement('div');
        wrap.id = 'gridScreenshotCloneWrap';

        const title = document.createElement('div');
        title.style.fontWeight = '800';
        title.style.fontSize = '16px';
        title.style.marginBottom = '10px';
        title.textContent = 'Campaign-wise Owner Grid';
        wrap.appendChild(title);

        const subtitle = document.createElement('div');
        subtitle.style.fontSize = '12px';
        subtitle.style.color = '#6b7280';
        subtitle.style.marginBottom = '14px';
        subtitle.textContent = 'Selected rows and columns only';
        wrap.appendChild(subtitle);

        const table = document.createElement('table');

        const thead = document.createElement('thead');
        const htr = document.createElement('tr');

        ctx.headers.forEach((h, idx) => {
            const th = document.createElement('th');
            th.textContent = h;

            if (idx === 0) th.className = 'campaign-name-cell';
            if (idx === 1) th.className = 'fresh-cell';

            if (idx >= 2) {
                const realIdx = ctx.selectedCols[idx - 2];
                const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                if (head) th.style.background = head.getAttribute('data-col-bg') || '#f3f4f6';
            }

            htr.appendChild(th);
        });

        thead.appendChild(htr);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        ctx.rows.forEach((tr, rowIndex) => {
            const row = document.createElement('tr');
            const rowBg = tr.getAttribute('data-row-bg') || '#ffffff';
            row.style.background = rowBg;

            const rowData = ctx.bodyRows[rowIndex];
            rowData.forEach((val, idx) => {
                const td = document.createElement('td');
                td.textContent = val;

                if (idx === 0) {
                    td.className = 'campaign-name-cell';
                    td.style.background = rowBg;
                } else if (idx === 1) {
                    td.className = 'fresh-cell';
                    td.style.background = rowBg;
                } else {
                    const realIdx = ctx.selectedCols[idx - 2];
                    const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                    td.style.background = head ? (head.getAttribute('data-col-bg') || '#f3f4f6') : '#f3f4f6';
                }

                row.appendChild(td);
            });

            tbody.appendChild(row);
        });

        const totalTr = document.createElement('tr');
        totalTr.className = 'total-row';

        ctx.totalsRow.forEach((val, idx) => {
            const td = document.createElement('td');
            td.textContent = val;

            if (idx === 0) {
                td.className = 'campaign-name-cell';
                td.style.background = '#f3f4f6';
            } else if (idx === 1) {
                td.className = 'fresh-cell';
                td.style.background = '#f3f4f6';
            } else {
                const realIdx = ctx.selectedCols[idx - 2];
                const head = document.querySelector('.owner-col-head[data-owner-col-index="' + realIdx + '"]');
                td.style.background = head ? (head.getAttribute('data-col-bg') || '#f3f4f6') : '#f3f4f6';
            }

            totalTr.appendChild(td);
        });

        tbody.appendChild(totalTr);
        table.appendChild(tbody);
        wrap.appendChild(table);
        document.body.appendChild(wrap);

        return wrap;
    }

    async function downloadGridScreenshot() {
        const ctx = getExportContext();
        const wrap = buildScreenshotTable(ctx);

        if (!wrap) {
            alert('No selected rows found.');
            return;
        }

        try {
            const canvas = await html2canvas(wrap, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true
            });

            const link = document.createElement('a');
            link.download = 'campaign_owner_grid_selected.png';
            link.href = canvas.toDataURL('image/png');
            document.body.appendChild(link);
            link.click();
            link.remove();
        } catch (err) {
            console.error(err);
            alert('Unable to generate screenshot.');
        } finally {
            wrap.remove();
        }
    }

    if (presetSelect) {
        presetSelect.addEventListener('change', function() {
            if (fromDt) fromDt.value = '';
            if (toDt) toDt.value = '';
        });
    }

    if (fromDt) {
        fromDt.addEventListener('input', function() {
            if (presetSelect) presetSelect.value = 'today_full';
        });
    }

    if (toDt) {
        toDt.addEventListener('input', function() {
            if (presetSelect) presetSelect.value = 'today_full';
        });
    }

    if (fillZeroBtn) {
        fillZeroBtn.addEventListener('click', function () {
            qsAll('.js-grid-owner').forEach(inp => {
                if ((inp.value || '').trim() === '') inp.value = '0';
                updateRowStatus(inp.closest('tr'));
            });
        });
    }

    qsAll('.js-grid-owner').forEach(bindGridInput);

    qsAll('.js-grid-row-check').forEach(cb => {
        cb.addEventListener('change', function () {
            const checkedCount = qsAll('.js-grid-row-check').filter(x => x.checked).length;
            if (!checkedCount) {
                this.checked = true;
                alert('At least one row must remain selected.');
                return;
            }
            updatePickedRowStyle();
        });
    });

    qsAll('.js-grid-col-check').forEach(cb => {
        cb.addEventListener('change', function () {
            const checkedCount = qsAll('.js-grid-col-check').filter(x => x.checked).length;
            if (!checkedCount) {
                this.checked = true;
                alert('At least one owner column must remain selected.');
                return;
            }
            applyColumnVisibility();
        });
    });

    if (btnSelectAllGridRows) {
        btnSelectAllGridRows.addEventListener('click', function () {
            qsAll('.js-grid-row-check').forEach(cb => cb.checked = true);
            updatePickedRowStyle();
        });
    }

    if (btnClearAllGridRows) {
        btnClearAllGridRows.addEventListener('click', function () {
            const all = qsAll('.js-grid-row-check');
            all.forEach(cb => cb.checked = false);
            if (all.length) all[0].checked = true;
            updatePickedRowStyle();
        });
    }

    if (btnSelectAllGridCols) {
        btnSelectAllGridCols.addEventListener('click', function () {
            qsAll('.js-grid-col-check').forEach(cb => cb.checked = true);
            applyColumnVisibility();
        });
    }

    if (btnClearAllGridCols) {
        btnClearAllGridCols.addEventListener('click', function () {
            const all = qsAll('.js-grid-col-check');
            all.forEach(cb => cb.checked = false);
            if (all.length) all[0].checked = true;
            applyColumnVisibility();
        });
    }

    if (btnExportGridCsv) {
        btnExportGridCsv.addEventListener('click', function () {
            const ctx = getExportContext();
            if (!ctx.bodyRows.length) {
                alert('No rows found to export.');
                return;
            }
            downloadCsv(
                'campaign_owner_grid_selected.csv',
                [ctx.headers].concat(ctx.bodyRows).concat([ctx.totalsRow])
            );
        });
    }

    if (btnExportGridExcel) {
        btnExportGridExcel.addEventListener('click', function () {
            const ctx = getExportContext();
            if (!ctx.bodyRows.length) {
                alert('No rows found to export.');
                return;
            }
            downloadExcel('campaign_owner_grid_selected_colored.xls');
        });
    }

    if (btnGridScreenshot) {
        btnGridScreenshot.addEventListener('click', function () {
            downloadGridScreenshot();
        });
    }

    if (btnApplyGrid) {
        btnApplyGrid.addEventListener('click', function () {
            const gridMap = collectGridMap();
            const validationErrors = validateGridAgainstLeads(gridMap);

            if (validationErrors.length) {
                alert('Grid has invalid counts:\n\n' + validationErrors.join('\n'));
                return;
            }

            clearUnSavedLeadOwners();

            let assigned = 0;
            let skipped  = 0;
            const campaignQueue = {};

            Object.keys(gridMap).forEach(k => {
                campaignQueue[k] = Array.isArray(gridMap[k]) ? gridMap[k].slice() : [];
            });

            qsAll('#leadsTable tbody tr[data-campaign-name]').forEach(tr => {
                if (tr.dataset.saved === '1') return;

                const campaignName = (tr.getAttribute('data-campaign-name') || '').trim();
                const sel = tr.querySelector('.js-owner');
                if (!sel || sel.disabled) return;

                if (!campaignQueue[campaignName] || !campaignQueue[campaignName].length) {
                    skipped++;
                    return;
                }

                const owner = campaignQueue[campaignName].shift();
                if (owner) {
                    sel.value = owner;
                    assigned++;
                } else {
                    skipped++;
                }
            });

            assignStatus.textContent = 'Applied from grid | Assigned: ' + assigned + ' | Fresh unassigned leads: ' + skipped;
            alert('Grid assignment completed.\nAssigned: ' + assigned + '\nFresh unassigned leads: ' + skipped);
        });
    }

    if (btnSaveSubmit) {
        btnSaveSubmit.addEventListener('click', async function () {
            const rows = qsAll('.js-owner').filter(sel => {
                const tr = sel.closest('tr');
                return tr && tr.dataset.saved !== '1' && !sel.disabled && (sel.value || '').trim() !== '';
            });

            if (!rows.length) {
                alert('No fresh assigned leads found to save.');
                return;
            }

            btnSaveSubmit.disabled = true;
            assignStatus.textContent = 'Saving started...';

            let okCount = 0;
            let failCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const sel = rows[i];
                const payload = buildPayloadFromSelect(sel);

                try {
                    await saveOne(payload);
                    okCount++;
                    markRowSaved(sel, 'Assigned');
                } catch (err) {
                    failCount++;
                    console.warn(err);
                }

                assignStatus.textContent = 'Saved ' + okCount + '/' + rows.length + (failCount ? ' | Failed: ' + failCount : '');
            }

            btnSaveSubmit.disabled = false;
            assignStatus.textContent = 'Completed | Success: ' + okCount + ' | Failed: ' + failCount;
            alert('Save completed.\nSuccess: ' + okCount + '\nFailed: ' + failCount);
        });
    }

    updatePickedRowStyle();
    applyColumnCellColors();
    applyColumnVisibility();
    updateGridTotals();
    updateFrontendTotalsRow();
})();
</script>

<?php include BASE_PATH . '/includes/footer.php'; ?>
