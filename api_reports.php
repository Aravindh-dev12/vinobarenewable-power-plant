<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
require 'config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');

function jsonFail($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message, 'data' => []]);
    exit;
}

function requestHeadersCompat() {
    if (function_exists('getallheaders')) return getallheaders();
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$key] = $value;
        }
    }
    return $headers;
}

function floorTimeLabel($timestamp, $minutes = 15) {
    $hour = (int)date('H', $timestamp);
    $minute = (int)date('i', $timestamp);
    $minute = (int)(floor($minute / $minutes) * $minutes);
    return sprintf('%02d:%02d', $hour, $minute);
}

function buildBuckets($type, $date, $chartMode = false) {
    $buckets = [];
    if ($type === 'daily') {
        if ($chartMode) {
            for ($h = 5; $h <= 19; $h++) {
                $label = sprintf('%02d:00', $h);
                $buckets[$label] = ['time_label' => $label, 'vcb_kwh' => 0, 'vcb_kw' => 0, 'tx_loss' => 0];
            }
        } else {
            $start = strtotime($date . ' 05:30:00');
            $end = strtotime($date . ' 19:30:00');
            for ($t = $start; $t <= $end; $t += 15 * 60) {
                $label = date('H:i', $t);
                $buckets[$label] = ['time_label' => $label, 'vcb_kwh' => 0, 'vcb_kw' => 0, 'tx_loss' => 0];
            }
        }
    } else {
        $monthStart = strtotime($date . '-01');
        $days = (int)date('t', $monthStart);
        for ($i = 1; $i <= $days; $i++) {
            $label = sprintf('%02d-%s', $i, date('m-Y', $monthStart));
            $buckets[$label] = ['time_label' => $label, 'vcb_kwh' => 0, 'vcb_kw' => 0, 'tx_loss' => 0];
        }
    }
    return $buckets;
}

function plantDisplayNames($conn) {
    $names = [];
    $res = $conn->query("SELECT id, name FROM plants");
    if ($res) {
        while ($row = $res->fetch_assoc()) $names[$row['id']] = $row['name'];
    }
    $fallback = [
        'vinoba-velliyanai' => 'Vinoba Velliyanai',
        'makkalpower' => 'Makkal Power',
        'anushyam' => 'Anushyam Plant'
    ];
    foreach ($fallback as $id => $name) if (!isset($names[$id])) $names[$id] = $name;
    return $names;
}

function loadInverterSeries($conn, $plant, $plantClause, $type, $date, $plantNames) {
    $series = [];
    $seen = [];
    if ($type === 'daily') {
        $rangeClause = "DATE(recorded_at)='" . $conn->real_escape_string($date) . "'";
    } else {
        $monthStart = $conn->real_escape_string($date . '-01');
        $nextMonth = date('Y-m-d', strtotime($date . '-01 +1 month'));
        $rangeClause = "recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonth 00:00:00'";
    }

    $sql = "SELECT DISTINCT plant_id, device_name FROM inverter_readings WHERE $rangeClause $plantClause AND device_name <> '' ORDER BY plant_id ASC, device_name ASC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $pid = $row['plant_id'];
            $device = trim($row['device_name']);
            $key = $pid . "\x1F" . $device;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $label = ($plant === 'all') ? (($plantNames[$pid] ?? $pid) . ' / ' . $device) : $device;
            $series[] = ['key' => $key, 'plant_id' => $pid, 'device_name' => $device, 'label' => $label];
        }
    }

    // If the selected day/month has no readings yet, preserve the plant's known inverter columns.
    if (empty($series)) {
        $sql = "SELECT plant_id, device_name, MAX(recorded_at) latest FROM inverter_readings WHERE device_name <> '' $plantClause GROUP BY plant_id, device_name ORDER BY plant_id ASC, device_name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $pid = $row['plant_id'];
                $device = trim($row['device_name']);
                $key = $pid . "\x1F" . $device;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $label = ($plant === 'all') ? (($plantNames[$pid] ?? $pid) . ' / ' . $device) : $device;
                $series[] = ['key' => $key, 'plant_id' => $pid, 'device_name' => $device, 'label' => $label];
            }
        }
    }

    if (empty($series) && $plant !== 'all') {
        $series[] = ['key' => $plant . "\x1FInverter 1", 'plant_id' => $plant, 'device_name' => 'Inverter 1', 'label' => 'Inverter 1'];
        $series[] = ['key' => $plant . "\x1FInverter 2", 'plant_id' => $plant, 'device_name' => 'Inverter 2', 'label' => 'Inverter 2'];
    }
    return $series;
}

function seriesIndexMap($series) {
    $map = [];
    foreach ($series as $i => $s) $map[$s['key']] = $i + 1;
    return $map;
}

function initializeSeriesFields(&$buckets, $seriesCount) {
    foreach ($buckets as &$bucket) {
        for ($i = 1; $i <= $seriesCount; $i++) {
            $bucket['inv' . $i . '_kwh'] = 0;
            $bucket['inv' . $i . '_kw'] = 0;
            $bucket['inv' . $i . '_temp'] = 0;
        }
        $bucket['inv_total_kwh'] = 0;
    }
    unset($bucket);
}

function finalizeTotals(&$buckets, $seriesCount) {
    foreach ($buckets as &$bucket) {
        $total = 0;
        for ($i = 1; $i <= $seriesCount; $i++) $total += (float)($bucket['inv' . $i . '_kwh'] ?? 0);
        $bucket['inv_total_kwh'] = $total;
        $bucket['tx_loss'] = $total - (float)($bucket['vcb_kwh'] ?? 0);
    }
    unset($bucket);
}

try {
    $headers = requestHeadersCompat();
    $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
    $userRole = '';
    $userPlant = '';

    if ($auth && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
        $token = $conn->real_escape_string($m[1]);
        $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token='$token' LIMIT 1");
        if ($res && $res->num_rows) {
            $u = $res->fetch_assoc();
            $userRole = $u['role'];
            $userPlant = $u['plant_id'];
        }
    }
    if (!$userRole && !empty($_GET['token'])) {
        $token = $conn->real_escape_string($_GET['token']);
        $res = $conn->query("SELECT role, plant_id FROM users WHERE auth_token='$token' LIMIT 1");
        if ($res && $res->num_rows) {
            $u = $res->fetch_assoc();
            $userRole = $u['role'];
            $userPlant = $u['plant_id'];
        }
    }

    $tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9_\-]/i', '', $_GET['tab']) : 'inv_vcb';
    $type = (isset($_GET['type']) && $_GET['type'] === 'monthly') ? 'monthly' : 'daily';
    $date = isset($_GET['date']) ? trim($_GET['date']) : ($type === 'daily' ? date('Y-m-d') : date('Y-m'));
    $plant = isset($_GET['plant']) ? trim($_GET['plant']) : 'all';
    $chartMode = isset($_GET['chart']);

    if ($type === 'daily' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonFail('Invalid daily report date.');
    if ($type === 'monthly' && !preg_match('/^\d{4}-\d{2}$/', $date)) jsonFail('Invalid monthly report month.');

    if ($userRole && $userRole !== 'admin' && $userPlant) $plant = $userPlant;
    if ($plant === '') $plant = 'all';
    $plantEsc = $conn->real_escape_string($plant);
    $plantClause = ($plant !== 'all') ? " AND plant_id='$plantEsc'" : '';

    $plantNames = plantDisplayNames($conn);
    $series = loadInverterSeries($conn, $plant, $plantClause, $type, $date, $plantNames);
    $seriesMap = seriesIndexMap($series);
    $invNames = array_map(function($s) { return $s['label']; }, $series);

    $buckets = buildBuckets($type, $date, $chartMode);
    initializeSeriesFields($buckets, count($series));

    if ($type === 'daily') {
        $dateEsc = $conn->real_escape_string($date);
        $bucketMinutes = $chartMode ? 60 : 15;

        $sql = "SELECT plant_id, device_name, recorded_at, daily_generation, ac_active_power, internal_temp FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' $plantClause ORDER BY recorded_at ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = $row['plant_id'] . "\x1F" . trim($row['device_name']);
                if (!isset($seriesMap[$key])) continue;
                $idx = $seriesMap[$key];
                $ts = strtotime($row['recorded_at']);
                $label = $chartMode ? date('H:00', $ts) : floorTimeLabel($ts, $bucketMinutes);
                if (!isset($buckets[$label])) continue;
                // Ordered ASC means each bucket ends with the latest reading in that slot.
                $buckets[$label]['inv' . $idx . '_kwh'] = (float)$row['daily_generation'];
                $buckets[$label]['inv' . $idx . '_kw'] = (float)$row['ac_active_power'];
                $buckets[$label]['inv' . $idx . '_temp'] = (float)$row['internal_temp'];
            }
        }

        // VCB export is cumulative. Calculate each plant against its own daily baseline,
        // then sum plant deltas per time bucket (important when plant=all).
        $vcbByBucketPlant = [];
        $baseline = [];
        $sql = "SELECT plant_id, recorded_at, active_power_total, active_total_export FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' $plantClause ORDER BY plant_id ASC, recorded_at ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $pid = $row['plant_id'];
                $export = (float)$row['active_total_export'];
                if (!isset($baseline[$pid])) $baseline[$pid] = $export;
                $delta = max($export - $baseline[$pid], 0) / 1000.0;
                $ts = strtotime($row['recorded_at']);
                $label = $chartMode ? date('H:00', $ts) : floorTimeLabel($ts, $bucketMinutes);
                if (!isset($buckets[$label])) continue;
                if (!isset($vcbByBucketPlant[$label])) $vcbByBucketPlant[$label] = [];
                $vcbByBucketPlant[$label][$pid] = ['kwh' => $delta, 'kw' => (float)$row['active_power_total']];
            }
        }
        foreach ($vcbByBucketPlant as $label => $plants) {
            $kwh = 0; $kw = 0;
            foreach ($plants as $v) { $kwh += $v['kwh']; $kw += $v['kw']; }
            $buckets[$label]['vcb_kwh'] = $kwh;
            $buckets[$label]['vcb_kw'] = $kw;
        }
    } else {
        $monthStart = $conn->real_escape_string($date . '-01');
        $nextMonth = date('Y-m-d', strtotime($date . '-01 +1 month'));

        $sql = "SELECT DATE(recorded_at) report_day, plant_id, device_name, MAX(daily_generation) kwh, MAX(ac_active_power) kw, MAX(internal_temp) temp FROM inverter_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonth 00:00:00' $plantClause GROUP BY DATE(recorded_at), plant_id, device_name ORDER BY report_day ASC, plant_id ASC, device_name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = $row['plant_id'] . "\x1F" . trim($row['device_name']);
                if (!isset($seriesMap[$key])) continue;
                $idx = $seriesMap[$key];
                $label = date('d-m-Y', strtotime($row['report_day']));
                if (!isset($buckets[$label])) continue;
                $buckets[$label]['inv' . $idx . '_kwh'] = (float)$row['kwh'];
                $buckets[$label]['inv' . $idx . '_kw'] = (float)$row['kw'];
                $buckets[$label]['inv' . $idx . '_temp'] = (float)$row['temp'];
            }
        }

        $sql = "SELECT report_day, SUM(kwh_exp) kwh_exp, SUM(max_kw) max_kw FROM (SELECT DATE(recorded_at) report_day, plant_id, GREATEST(MAX(active_total_export)-MIN(active_total_export),0) kwh_exp, MAX(active_power_total) max_kw FROM vcb_readings WHERE recorded_at >= '$monthStart 00:00:00' AND recorded_at < '$nextMonth 00:00:00' $plantClause GROUP BY DATE(recorded_at), plant_id) x GROUP BY report_day ORDER BY report_day ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $label = date('d-m-Y', strtotime($row['report_day']));
                if (!isset($buckets[$label])) continue;
                $buckets[$label]['vcb_kwh'] = ((float)$row['kwh_exp']) / 1000.0;
                $buckets[$label]['vcb_kw'] = (float)$row['max_kw'];
            }
        }
    }

    finalizeTotals($buckets, count($series));

    $operatingTimes = [
        'inverter' => ['start' => null, 'end' => null],
        'vcb' => ['start' => null, 'end' => null],
        'transformer' => ['start' => null, 'end' => null]
    ];
    if ($type === 'daily') {
        $dateEsc = $conn->real_escape_string($date);
        $queries = [
            'inverter' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') end_time FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' AND ac_active_power > 0 $plantClause",
            'vcb' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') end_time FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' AND active_power_total > 0 $plantClause",
            'transformer' => "SELECT DATE_FORMAT(MIN(recorded_at),'%H:%i') start_time, DATE_FORMAT(MAX(recorded_at),'%H:%i') end_time FROM transformer_readings WHERE DATE(recorded_at)='$dateEsc' $plantClause"
        ];
        foreach ($queries as $key => $sql) {
            $r = $conn->query($sql);
            if ($r && $r->num_rows) {
                $row = $r->fetch_assoc();
                $operatingTimes[$key] = ['start' => $row['start_time'] ?: null, 'end' => $row['end_time'] ?: null];
            }
        }
    }

    $latestAt = null;
    if ($type === 'daily') {
        $dateEsc = $conn->real_escape_string($date);
        $r = $conn->query("SELECT MAX(recorded_at) latest_at FROM (SELECT recorded_at FROM inverter_readings WHERE DATE(recorded_at)='$dateEsc' $plantClause UNION ALL SELECT recorded_at FROM vcb_readings WHERE DATE(recorded_at)='$dateEsc' $plantClause) q");
        if ($r && $r->num_rows) $latestAt = $r->fetch_assoc()['latest_at'];
    }
    $isToday = ($type === 'daily' && $date === date('Y-m-d'));
    $isFresh = $latestAt && (time() - strtotime($latestAt) <= 600);

    echo json_encode([
        'success' => true,
        'meta' => [
            'tab' => $tab,
            'type' => $type,
            'date' => $date,
            'plant' => $plant,
            'source' => ($isToday && $isFresh) ? 'db_live' : 'db_cache',
            'latest_at' => $latestAt,
            'inv_names' => $invNames,
            'operating_times' => $operatingTimes,
            'generated_at' => date('Y-m-d H:i:s')
        ],
        'data' => array_values($buckets)
    ]);
} catch (Throwable $e) {
    error_log('[api_reports] ' . $e->getMessage());
    jsonFail('Server error: ' . $e->getMessage(), 500);
}
?>
