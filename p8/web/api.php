<?php
// ============================================================
// api.php — REST API endpoint (JSON) untuk dashboard
// ============================================================

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? 'latest';

try {
    $db = getDB();

    switch ($action) {
        case 'latest':
            $row = $db->query("SELECT * FROM v_ticker_with_indicators LIMIT 1")->fetch();
            echo json_encode(['ok' => true, 'data' => $row]);
            break;

        case 'chart':
            $limit = min((int)($_GET['limit'] ?? 100), 500);
            $stmt  = $db->prepare("
                SELECT created_at, last, rsi, macd_line, macd_signal, macd_histogram,
                       bb_upper, bb_middle, bb_lower, stoch_k, stoch_d,
                       parabolic_sar, sar_trend, signal, signal_score
                FROM v_ticker_with_indicators LIMIT :lim
            ");
            $stmt->execute([':lim' => $limit]);
            $rows = array_reverse($stmt->fetchAll());
            echo json_encode(['ok' => true, 'data' => $rows]);
            break;

        case 'stats':
            $row = $db->query("
                SELECT COUNT(*) total, MIN(last) price_min, MAX(last) price_max, AVG(last)::NUMERIC(20,2) price_avg
                FROM tickers_indodax WHERE pair='btc_idr' AND created_at > NOW() - INTERVAL '1 hour'
            ")->fetch();
            echo json_encode(['ok' => true, 'data' => $row]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
