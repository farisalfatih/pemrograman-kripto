<?php
// ============================================================
// fetcher.php — Daemon: Fetch Indodax API tiap 5 detik,
//               hitung semua indikator, simpan ke PostgreSQL
// Dijalankan oleh container 'fetcher' di Docker Compose
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Indicators.php';

define('FETCH_URL',     'https://indodax.com/api/tickers/btc_idr');
define('FETCH_INTERVAL', 5);
define('MIN_POINTS',    60);   // minimal data sebelum hitung indikator

echo "[" . date('Y-m-d H:i:s') . "] Fetcher BTC/IDR — STARTED\n";

// ── Ambil ticker dari Indodax ─────────────────────────────────
function fetchTicker(): ?array {
    $ctx = stream_context_create(['http' => [
        'timeout'       => 4,
        'user_agent'    => 'BTC-Tracker/1.0',
        'ignore_errors' => true,
    ]]);
    $raw  = @file_get_contents(FETCH_URL, false, $ctx);
    if (!$raw) return null;
    $json = json_decode($raw, true);
    return $json['ticker'] ?? null;
}

// ── Insert ticker, kembalikan ID ──────────────────────────────
function insertTicker(PDO $db, array $t): int {
    $stmt = $db->prepare("
        INSERT INTO tickers_indodax
            (pair, buy, sell, low, high, last, server_time, vol_coin, vol_idr)
        VALUES
            ('btc_idr', :buy, :sell, :low, :high, :last, :st, :vc, :vi)
        RETURNING id
    ");
    $stmt->execute([
        ':buy'  => floatval($t['buy']  ?? 0),
        ':sell' => floatval($t['sell'] ?? 0),
        ':low'  => floatval($t['low']  ?? 0),
        ':high' => floatval($t['high'] ?? 0),
        ':last' => floatval($t['last'] ?? 0),
        ':st'   => intval($t['server_time'] ?? time()),
        ':vc'   => floatval($t['vol_btc']   ?? 0),
        ':vi'   => floatval($t['vol_idr']   ?? 0),
    ]);
    return (int) $stmt->fetchColumn();
}

// ── Ambil riwayat untuk kalkulasi ─────────────────────────────
function getHistory(PDO $db, int $limit = 150): array {
    $rows = $db->query("
        SELECT last, high, low FROM tickers_indodax
        WHERE pair='btc_idr'
        ORDER BY created_at DESC LIMIT $limit
    ")->fetchAll();
    $rows = array_reverse($rows);
    return [
        'closes' => array_map(fn($r) => floatval($r['last']), $rows),
        'highs'  => array_map(fn($r) => floatval($r['high']), $rows),
        'lows'   => array_map(fn($r) => floatval($r['low']),  $rows),
    ];
}

// ── Hitung & simpan indikator ─────────────────────────────────
function calcAndSave(PDO $db, int $tickerId, float $price): void {
    $h = getHistory($db, 150);
    $n = count($h['closes']);
    if ($n < MIN_POINTS) {
        echo "  Akumulasi data: $n/" . MIN_POINTS . "\n";
        return;
    }

    $rsi   = Indicators::rsi($h['closes']);
    $macd  = Indicators::macd($h['closes']);
    $bb    = Indicators::bollingerBands($h['closes']);
    $stoch = Indicators::stochastic($h['highs'], $h['lows'], $h['closes']);
    $sar   = Indicators::parabolicSAR($h['highs'], $h['lows']);
    $sig   = Indicators::generateSignal($price, $rsi, $macd, $bb, $stoch, $sar);

    $db->prepare("
        INSERT INTO indicators (
            ticker_id, rsi,
            macd_line, macd_signal, macd_histogram,
            bb_upper, bb_middle, bb_lower, bb_width,
            stoch_k, stoch_d,
            parabolic_sar, sar_trend,
            signal, signal_score, signal_detail
        ) VALUES (
            :tid, :rsi,
            :ml, :ms, :mh,
            :bbu, :bbm, :bbl, :bbw,
            :sk, :sd,
            :psar, :sart,
            :signal, :score, :detail
        )
    ")->execute([
        ':tid'    => $tickerId,
        ':rsi'    => $rsi,
        ':ml'     => $macd['macd']      ?? null,
        ':ms'     => $macd['signal']    ?? null,
        ':mh'     => $macd['histogram'] ?? null,
        ':bbu'    => $bb['upper']       ?? null,
        ':bbm'    => $bb['middle']      ?? null,
        ':bbl'    => $bb['lower']       ?? null,
        ':bbw'    => $bb['width']       ?? null,
        ':sk'     => $stoch['k']        ?? null,
        ':sd'     => $stoch['d']        ?? null,
        ':psar'   => $sar['sar']        ?? null,
        ':sart'   => $sar['trend']      ?? null,
        ':signal' => $sig['signal'],
        ':score'  => $sig['score'],
        ':detail' => json_encode($sig['detail']),
    ]);

    printf(
        "  Harga: %s | RSI: %s | Signal: %-12s [%+d]\n",
        number_format($price, 0, ',', '.'),
        $rsi ? number_format($rsi, 2) : 'N/A',
        $sig['signal'],
        $sig['score']
    );
}

// ── MAIN LOOP ─────────────────────────────────────────────────
$db   = null;
$iter = 0;

while (true) {
    $start = microtime(true);
    $iter++;
    echo "[" . date('H:i:s') . "] #$iter\n";

    try {
        if ($db === null) $db = getDB();

        $ticker = fetchTicker();
        if ($ticker) {
            $price = floatval($ticker['last'] ?? 0);
            $id    = insertTicker($db, $ticker);
            calcAndSave($db, $id, $price);
        } else {
            echo "  [WARN] Gagal fetch API\n";
        }
    } catch (Throwable $e) {
        echo "  [ERROR] " . $e->getMessage() . "\n";
        $db = null; // reset koneksi
        sleep(2);
    }

    $elapsed = microtime(true) - $start;
    $sleep   = FETCH_INTERVAL - $elapsed;
    if ($sleep > 0) usleep((int)($sleep * 1_000_000));
}
