<?php
// ============================================================
// index.php — Dashboard + Chart semua indikator + Tabel Pagination
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Indicators.php';

try {
    $db   = getDB();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk  = false;
    $dbErr = $e->getMessage();
}

$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$totalRows  = 0;
$totalPages = 1;
$rows       = [];
$latest     = null;
$summary    = ['vol_coin' => 0, 'vol_idr' => 0];
$chartData  = [];

if ($dbOk) {
    $totalRows  = (int) $db->query("SELECT COUNT(*) FROM tickers_indodax WHERE pair='btc_idr'")->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $limit));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $limit;

    // Tabel halaman ini
    $stmt = $db->prepare("
        SELECT t.id, t.pair, t.buy, t.sell, t.low, t.high, t.last,
               t.server_time, t.vol_coin, t.vol_idr, t.created_at,
               i.rsi, i.macd_line, i.macd_histogram,
               i.bb_upper, i.bb_lower, i.bb_middle,
               i.stoch_k, i.stoch_d,
               i.parabolic_sar, i.sar_trend, i.signal, i.signal_score
        FROM tickers_indodax t
        LEFT JOIN indicators i ON i.ticker_id = t.id
        WHERE t.pair='btc_idr'
        ORDER BY t.created_at DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->execute([':lim' => $limit, ':off' => $offset]);
    $rows = $stmt->fetchAll();

    $summary['vol_coin'] = array_sum(array_column($rows, 'vol_coin'));
    $summary['vol_idr']  = array_sum(array_column($rows, 'vol_idr'));

    // Data terbaru untuk stat cards
    $latest = $db->query("SELECT * FROM v_ticker_with_indicators LIMIT 1")->fetch();

    // Data chart — 100 titik terbaru, dibalik agar urut waktu
    $cstmt = $db->query("
        SELECT
            to_char(t.created_at AT TIME ZONE 'Asia/Jakarta', 'HH24:MI:SS') AS label,
            t.last, t.high, t.low,
            i.rsi,
            i.macd_line, i.macd_signal, i.macd_histogram,
            i.bb_upper, i.bb_middle, i.bb_lower,
            i.stoch_k, i.stoch_d,
            i.parabolic_sar, i.sar_trend
        FROM tickers_indodax t
        LEFT JOIN indicators i ON i.ticker_id = t.id
        WHERE t.pair='btc_idr'
        ORDER BY t.created_at DESC LIMIT 100
    ");
    $chartData = array_reverse($cstmt->fetchAll());
}

// ── Helpers ───────────────────────────────────────────────────
function rp(float $v, int $d = 0): string {
    return number_format($v, $d, ',', '.');
}
function signalClass(string $s): string {
    return match($s) {
        'STRONG_BUY'  => 'sbadge-sbuy',
        'BUY'         => 'sbadge-buy',
        'SELL'        => 'sbadge-sell',
        'STRONG_SELL' => 'sbadge-ssell',
        default       => 'sbadge-neutral',
    };
}
function signalLabel(string $s): string { return str_replace('_',' ',$s); }

// ── Siapkan data JSON untuk Chart.js ─────────────────────────
$cd = $chartData;
$js = [
    'labels'    => array_column($cd, 'label'),
    'price'     => array_map(fn($r) => (float)$r['last'],         $cd),
    'high'      => array_map(fn($r) => (float)$r['high'],         $cd),
    'low'       => array_map(fn($r) => (float)$r['low'],          $cd),
    'bbU'       => array_map(fn($r) => $r['bb_upper']  ? (float)$r['bb_upper']  : null, $cd),
    'bbM'       => array_map(fn($r) => $r['bb_middle'] ? (float)$r['bb_middle'] : null, $cd),
    'bbL'       => array_map(fn($r) => $r['bb_lower']  ? (float)$r['bb_lower']  : null, $cd),
    'rsi'       => array_map(fn($r) => $r['rsi']       ? (float)$r['rsi']       : null, $cd),
    'macdL'     => array_map(fn($r) => $r['macd_line']      ? (float)$r['macd_line']      : null, $cd),
    'macdS'     => array_map(fn($r) => $r['macd_signal']    ? (float)$r['macd_signal']    : null, $cd),
    'macdH'     => array_map(fn($r) => $r['macd_histogram'] ? (float)$r['macd_histogram'] : null, $cd),
    'stochK'    => array_map(fn($r) => $r['stoch_k'] ? (float)$r['stoch_k'] : null, $cd),
    'stochD'    => array_map(fn($r) => $r['stoch_d'] ? (float)$r['stoch_d'] : null, $cd),
    'sar'       => array_map(fn($r) => $r['parabolic_sar'] ? (float)$r['parabolic_sar'] : null, $cd),
    'sarTrend'  => array_column($cd, 'sar_trend'),
];
$jsData = json_encode($js);

// Stat cards
$price    = floatval($latest['last']        ?? 0);
$buy      = floatval($latest['buy']         ?? 0);
$sell     = floatval($latest['sell']        ?? 0);
$high24   = floatval($latest['high']        ?? 0);
$low24    = floatval($latest['low']         ?? 0);
$volBtc   = floatval($latest['vol_coin']    ?? 0);
$rsi      = floatval($latest['rsi']         ?? 0);
$macdH    = floatval($latest['macd_histogram'] ?? 0);
$bbU      = floatval($latest['bb_upper']    ?? 0);
$bbM      = floatval($latest['bb_middle']   ?? 0);
$bbL      = floatval($latest['bb_lower']    ?? 0);
$stochK   = floatval($latest['stoch_k']     ?? 0);
$stochD   = floatval($latest['stoch_d']     ?? 0);
$psar     = floatval($latest['parabolic_sar'] ?? 0);
$sarTrend = $latest['sar_trend']            ?? '—';
$sig      = $latest['signal']               ?? 'N/A';
$score    = intval($latest['signal_score']  ?? 0);
$rsiZone  = $rsi < 30 ? 'Oversold' : ($rsi > 70 ? 'Overbought' : 'Normal');
$rsiColor = $rsi < 30 ? '#22c55e' : ($rsi > 70 ? '#ef4444' : '#eab308');

$pStart = max(1, $page - 4);
$pEnd   = min($totalPages, $page + 5);
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BTC/IDR — Real-Time Trading Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box}
body{background:#0f1117;color:#e2e8f0;font-family:'Segoe UI',system-ui,sans-serif;margin:0}

/* Navbar */
.navbar-custom{background:#1a1f2e;border-bottom:1px solid #2d3748;padding:11px 0}
.brand-icon{width:30px;height:30px;border-radius:50%;background:#f7931a;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;margin-right:8px}
.brand-text{font-size:16px;font-weight:700;color:#f7931a}
.brand-sub{font-size:10px;color:#718096}
.live-badge{background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.3);padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600}
.live-dot{width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;margin-right:4px;animation:blink 1.5s infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* Stat Cards */
.stat-card{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:14px 18px;height:100%}
.stat-label{font-size:9px;color:#718096;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px}
.stat-value{font-size:19px;font-weight:700;line-height:1.1}
.stat-sub{font-size:10px;color:#718096;margin-top:3px}

/* Indicator Cards */
.ind-card{background:#1a1f2e;border:1px solid #2d3748;border-radius:10px;padding:12px 14px;height:100%}
.ind-title{font-size:9px;color:#718096;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;font-weight:600}
.ind-row{display:flex;justify-content:space-between;align-items:center;padding:2px 0;font-size:12px}
.ind-key{color:#718096}
.ind-val{font-weight:600;font-family:monospace}
.gauge{height:3px;background:#2d3748;border-radius:2px;margin-top:6px;overflow:hidden}
.gauge-fill{height:100%;border-radius:2px;transition:width .5s}

/* Signal Badges */
.sbadge-sbuy  {background:rgba(34,197,94,.2);color:#22c55e;border:1px solid rgba(34,197,94,.4)}
.sbadge-buy   {background:rgba(134,239,172,.12);color:#86efac;border:1px solid rgba(134,239,172,.3)}
.sbadge-neutral{background:rgba(234,179,8,.12);color:#eab308;border:1px solid rgba(234,179,8,.3)}
.sbadge-sell  {background:rgba(252,165,165,.12);color:#fca5a5;border:1px solid rgba(252,165,165,.3)}
.sbadge-ssell {background:rgba(239,68,68,.2);color:#ef4444;border:1px solid rgba(239,68,68,.4)}
.sig-badge{padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;white-space:nowrap}

/* Chart Section */
.chart-card{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;padding:14px 16px;margin-bottom:14px}
.chart-title{font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.8px;font-weight:600;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.chart-dot{width:8px;height:8px;border-radius:50%;display:inline-block}

/* Table */
.table-wrap{background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;overflow:hidden}
.tbl-header{padding:12px 18px;border-bottom:1px solid #2d3748;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.tbl-title{font-size:13px;font-weight:600}
.table{--bs-table-bg:transparent;--bs-table-striped-bg:rgba(255,255,255,.025);--bs-table-hover-bg:rgba(255,255,255,.04);--bs-table-color:#e2e8f0;--bs-table-border-color:#2d3748;margin:0}
.table thead th{background:#111827;color:#9ca3af;font-size:10px;text-transform:uppercase;letter-spacing:.8px;font-weight:600;border-color:#2d3748;padding:9px 10px;white-space:nowrap;text-align:center}
.table tbody td{font-size:11px;padding:8px 10px;text-align:center;vertical-align:middle;border-color:#2d3748;font-family:monospace}
.table tbody tr:last-child td{border-bottom:0}

/* Colors */
.v-green{color:#22c55e}.v-red{color:#ef4444}.v-amber{color:#eab308}.v-blue{color:#60a5fa}.v-muted{color:#718096}.v-orange{color:#f7931a}

/* Pagination */
.pagination .page-link{background:#1a1f2e;border-color:#2d3748;color:#9ca3af;font-size:12px}
.pagination .page-item.active .page-link{background:#f7931a;border-color:#f7931a;color:#000;font-weight:600}
.pagination .page-link:hover{background:#2d3748;color:#e2e8f0}

/* Sum badge */
.sum-badge{padding:4px 12px;border-radius:5px;font-size:11px;font-weight:600}

/* Section label */
.section-sep{font-size:10px;color:#718096;text-transform:uppercase;letter-spacing:1px;font-weight:600;padding:6px 0;border-bottom:1px solid #2d3748;margin-bottom:12px}

footer{border-top:1px solid #2d3748;padding:12px 0;color:#4a5568;font-size:11px}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar-custom">
  <div class="container-fluid px-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center">
          <span class="brand-icon">₿</span>
          <div><div class="brand-text">BTC / IDR</div><div class="brand-sub">Indodax · Real-Time Monitor</div></div>
        </div>
        <span class="live-badge"><span class="live-dot"></span>LIVE</span>
      </div>
      <div class="v-muted" style="font-size:11px">
        <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i:s') ?>
        &nbsp;·&nbsp; Auto-refresh 5s
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 py-3">
<?php if (!$dbOk): ?>
  <div class="alert alert-danger mt-2"><i class="bi bi-exclamation-triangle me-2"></i>DB Error: <?= htmlspecialchars($dbErr) ?></div>
<?php else: ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- STAT CARDS                                                  -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-2 mb-3">
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-currency-bitcoin me-1"></i>Harga Terakhir</div>
      <div class="stat-value v-orange">Rp <?= rp($price) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-arrow-up-circle me-1"></i>High 24j</div>
      <div class="stat-value v-green">Rp <?= rp($high24) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-arrow-down-circle me-1"></i>Low 24j</div>
      <div class="stat-value v-red">Rp <?= rp($low24) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-arrow-left-right me-1"></i>Bid / Ask</div>
      <div class="stat-value" style="font-size:13px;line-height:1.6">
        <span class="v-green">Rp <?= rp($buy) ?></span><br>
        <span class="v-red">Rp <?= rp($sell) ?></span>
      </div>
      <div class="stat-sub">Spread: Rp <?= rp($sell - $buy) ?></div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-box me-1"></i>Volume BTC</div>
      <div class="stat-value v-blue"><?= number_format($volBtc, 4) ?></div>
      <div class="stat-sub">BTC / 24j</div>
    </div>
  </div>
  <div class="col-6 col-sm-4 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-database me-1"></i>Total Data DB</div>
      <div class="stat-value" style="color:#a78bfa"><?= number_format($totalRows) ?></div>
      <div class="stat-sub">Ticker tersimpan</div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- SINYAL + INDIKATOR CARDS                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row g-2 mb-3">

  <!-- Sinyal Komposit -->
  <div class="col-md-2 col-6">
    <div class="ind-card text-center d-flex flex-column justify-content-center gap-2">
      <div class="ind-title">Sinyal Komposit</div>
      <?php if ($sig !== 'N/A'): ?>
        <div><span class="sig-badge <?= signalClass($sig) ?>" style="font-size:14px;padding:8px 18px">
          <?= signalLabel($sig) ?></span></div>
        <div style="font-size:22px;font-weight:700;color:<?= $score >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= ($score > 0 ? '+' : '') . $score ?>
        </div>
        <div class="v-muted" style="font-size:10px">dari 5 indikator</div>
      <?php else: ?>
        <div class="v-muted" style="font-size:11px">Akumulasi data...</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RSI -->
  <div class="col-md col-6">
    <div class="ind-card">
      <div class="ind-title">RSI (14)</div>
      <div class="ind-row"><span class="ind-key">Nilai</span>
        <span class="ind-val" style="color:<?= $rsiColor ?>"><?= $rsi ? number_format($rsi, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Zona</span>
        <span class="ind-val" style="color:<?= $rsiColor ?>"><?= $rsiZone ?></span></div>
      <div class="gauge"><div class="gauge-fill" style="width:<?= min(100,$rsi) ?>%;background:<?= $rsiColor ?>"></div></div>
      <div class="d-flex justify-content-between" style="font-size:8px;color:#4a5568;margin-top:2px">
        <span>0</span><span>30</span><span>70</span><span>100</span></div>
    </div>
  </div>

  <!-- MACD -->
  <div class="col-md col-6">
    <div class="ind-card">
      <div class="ind-title">MACD (12,26,9)</div>
      <div class="ind-row"><span class="ind-key">Line</span>
        <span class="ind-val"><?= $latest['macd_line'] ? rp(floatval($latest['macd_line'])) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Histogram</span>
        <span class="ind-val" style="color:<?= $macdH >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= $latest['macd_histogram'] ? (($macdH > 0 ? '+' : '') . rp($macdH)) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Tren</span>
        <span class="ind-val" style="color:<?= $macdH >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= $latest['macd_histogram'] ? ($macdH >= 0 ? 'Bullish ↑' : 'Bearish ↓') : '—' ?></span></div>
    </div>
  </div>

  <!-- Bollinger Bands -->
  <div class="col-md col-6">
    <div class="ind-card">
      <div class="ind-title">Bollinger Bands (20)</div>
      <div class="ind-row"><span class="ind-key">Upper</span>
        <span class="ind-val v-red"><?= $bbU ? 'Rp '.rp($bbU) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Middle</span>
        <span class="ind-val v-blue"><?= $bbM ? 'Rp '.rp($bbM) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Lower</span>
        <span class="ind-val v-green"><?= $bbL ? 'Rp '.rp($bbL) : '—' ?></span></div>
    </div>
  </div>

  <!-- Stochastic -->
  <div class="col-md col-6">
    <div class="ind-card">
      <div class="ind-title">Stochastic (14,3)</div>
      <div class="ind-row"><span class="ind-key">%K</span>
        <span class="ind-val"><?= $stochK ? number_format($stochK, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">%D</span>
        <span class="ind-val"><?= $stochD ? number_format($stochD, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Zona</span>
        <span class="ind-val" style="color:<?= $stochK < 20 ? '#22c55e' : ($stochK > 80 ? '#ef4444' : '#eab308') ?>">
          <?= $stochK ? ($stochK < 20 ? 'Oversold' : ($stochK > 80 ? 'Overbought' : 'Normal')) : '—' ?></span></div>
      <div class="gauge"><div class="gauge-fill" style="width:<?= min(100,$stochK) ?>%;background:#a855f7"></div></div>
    </div>
  </div>

  <!-- Parabolic SAR -->
  <div class="col-md col-6">
    <div class="ind-card">
      <div class="ind-title">Parabolic SAR</div>
      <div class="ind-row"><span class="ind-key">SAR</span>
        <span class="ind-val"><?= $psar ? 'Rp '.rp($psar) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Tren</span>
        <span class="ind-val" style="color:<?= $sarTrend === 'UP' ? '#22c55e' : ($sarTrend === 'DOWN' ? '#ef4444' : '#718096') ?>">
          <?= $sarTrend ?> <?= $sarTrend === 'UP' ? '↑' : ($sarTrend === 'DOWN' ? '↓' : '') ?></span></div>
      <div class="ind-row"><span class="ind-key">Posisi</span>
        <span class="ind-val" style="font-size:10px;color:<?= ($psar && $price) ? ($psar < $price ? '#22c55e' : '#ef4444') : '#718096' ?>">
          <?= $psar && $price ? ($psar < $price ? 'SAR di Bawah' : 'SAR di Atas') : '—' ?></span></div>
    </div>
  </div>

</div><!-- /ind row -->

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CHART SECTION                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="section-sep mb-3">
  <i class="bi bi-bar-chart-line me-2"></i>Grafik Indikator Teknikal
  <span class="ms-2 v-muted" style="font-size:9px;font-weight:400">
    (<?= count($chartData) ?> data terakhir · update tiap 5 detik)
  </span>
</div>

<!-- Chart 1: Harga + Bollinger Bands -->
<div class="chart-card">
  <div class="chart-title">
    <span class="chart-dot" style="background:#f7931a"></span> Harga BTC/IDR &amp; Bollinger Bands
  </div>
  <div style="position:relative;height:220px">
    <canvas id="chartPrice"></canvas>
  </div>
</div>

<!-- Chart 2 + 3 side by side -->
<div class="row g-2 mb-2">
  <div class="col-md-6">
    <div class="chart-card mb-0">
      <div class="chart-title">
        <span class="chart-dot" style="background:#a855f7"></span> RSI (14) — Relative Strength Index
      </div>
      <div style="position:relative;height:180px">
        <canvas id="chartRsi"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="chart-card mb-0">
      <div class="chart-title">
        <span class="chart-dot" style="background:#3b82f6"></span> MACD (12, 26, 9)
      </div>
      <div style="position:relative;height:180px">
        <canvas id="chartMacd"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Chart 4 + 5 side by side -->
<div class="row g-2 mb-3">
  <div class="col-md-6">
    <div class="chart-card mb-0">
      <div class="chart-title">
        <span class="chart-dot" style="background:#eab308"></span> Stochastic Oscillator (%K &amp; %D)
      </div>
      <div style="position:relative;height:180px">
        <canvas id="chartStoch"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="chart-card mb-0">
      <div class="chart-title">
        <span class="chart-dot" style="background:#22c55e"></span> Parabolic SAR vs Harga
      </div>
      <div style="position:relative;height:180px">
        <canvas id="chartSar"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TABEL DATA                                                  -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="section-sep mb-3"><i class="bi bi-table me-2"></i>Data Ticker BTC/IDR</div>

<div class="table-wrap mb-4">
  <div class="tbl-header">
    <div>
      <div class="tbl-title"><i class="bi bi-table me-1"></i> Riwayat Ticker</div>
      <div style="font-size:11px;color:#718096;margin-top:1px">
        Halaman <?= $page ?> dari <?= $totalPages ?> · <?= number_format($totalRows) ?> total data
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <span class="sum-badge" style="background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.2)">
        <i class="bi bi-database me-1"></i><?= number_format($totalRows) ?> data</span>
      <span class="sum-badge" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.2)">
        <i class="bi bi-coin me-1"></i><?= number_format($summary['vol_coin'], 4) ?> BTC</span>
      <span class="sum-badge" style="background:rgba(234,179,8,.12);color:#eab308;border:1px solid rgba(234,179,8,.2)">
        <i class="bi bi-currency-exchange me-1"></i>Rp <?= number_format($summary['vol_idr'], 0) ?></span>
    </div>
  </div>

  <!-- Pagination atas -->
  <div class="px-3 pt-2">
    <nav><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap gap-1">
      <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i></a></li>
      <?php endif; ?>
      <?php if ($pStart > 1): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
      <?php endif; ?>
      <?php for ($i = $pStart; $i <= $pEnd; $i++): ?>
        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <?php if ($pEnd < $totalPages): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>"><i class="bi bi-chevron-right"></i></a></li>
      <?php endif; ?>
    </ul></nav>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0">
      <thead><tr>
        <th>#</th><th>Pair</th>
        <th>Buy</th><th>Sell</th><th>Low</th><th>High</th><th>Last</th>
        <th>Waktu Server</th><th>Vol BTC</th><th>Vol IDR</th>
        <th>RSI</th><th>MACD Hist</th><th>BB Posisi</th>
        <th>Stoch %K</th><th>SAR Tren</th><th>Signal</th>
      </tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="16" class="text-center py-4 v-muted">
          <i class="bi bi-hourglass me-2"></i>Menunggu data...</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $r):
          $rV   = $r['rsi']            ? floatval($r['rsi'])            : null;
          $mHV  = $r['macd_histogram'] ? floatval($r['macd_histogram']) : null;
          $skV  = $r['stoch_k']        ? floatval($r['stoch_k'])        : null;
          $lV   = floatval($r['last']);
          $bUV  = $r['bb_upper']  ? floatval($r['bb_upper'])  : null;
          $bLV  = $r['bb_lower']  ? floatval($r['bb_lower'])  : null;
          $bPos = ($bUV && $bLV) ? ($lV >= $bUV ? 'AT UPPER' : ($lV <= $bLV ? 'AT LOWER' : 'INSIDE')) : '—';
          $bCol = $bPos === 'AT UPPER' ? '#ef4444' : ($bPos === 'AT LOWER' ? '#22c55e' : '#eab308');
        ?>
        <tr>
          <td class="v-muted"><?= $offset + $i + 1 ?></td>
          <td><span style="background:rgba(247,147,26,.15);color:#f7931a;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600">
            <?= strtoupper(htmlspecialchars($r['pair'])) ?></span></td>
          <td class="v-green"><?= rp($r['buy']) ?></td>
          <td class="v-red"><?= rp($r['sell']) ?></td>
          <td><?= rp($r['low']) ?></td>
          <td><?= rp($r['high']) ?></td>
          <td class="v-orange" style="font-weight:600"><?= rp($r['last']) ?></td>
          <td class="v-muted" style="font-size:10px"><?= date('d/m H:i:s', $r['server_time']) ?></td>
          <td class="v-blue"><?= number_format($r['vol_coin'], 4) ?></td>
          <td class="v-muted" style="font-size:10px"><?= rp($r['vol_idr']) ?></td>
          <td style="color:<?= $rV ? ($rV<30?'#22c55e':($rV>70?'#ef4444':'#eab308')) : '#718096' ?>">
            <?= $rV ? number_format($rV, 2) : '—' ?></td>
          <td style="color:<?= $mHV !== null ? ($mHV>=0?'#22c55e':'#ef4444') : '#718096' ?>">
            <?= $mHV !== null ? (($mHV>0?'+':'').rp($mHV)) : '—' ?></td>
          <td style="color:<?= $bCol ?>;font-size:10px"><?= $bPos ?></td>
          <td style="color:<?= $skV ? ($skV<20?'#22c55e':($skV>80?'#ef4444':'#eab308')) : '#718096' ?>">
            <?= $skV ? number_format($skV, 2) : '—' ?></td>
          <td>
            <?php if ($r['sar_trend']): ?>
              <span style="color:<?= $r['sar_trend']==='UP'?'#22c55e':'#ef4444' ?>;font-weight:600">
                <?= $r['sar_trend'] ?> <?= $r['sar_trend']==='UP'?'↑':'↓' ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php if ($r['signal']): ?>
              <span class="sig-badge <?= signalClass($r['signal']) ?>"><?= signalLabel($r['signal']) ?></span>
            <?php else: ?>
              <span class="v-muted" style="font-size:10px">Akumulasi...</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination bawah -->
  <div class="px-3 py-2">
    <nav><ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap gap-1">
      <?php if ($page > 1): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>"><i class="bi bi-chevron-left"></i></a></li>
      <?php endif; ?>
      <?php for ($i = $pStart; $i <= $pEnd; $i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>"><i class="bi bi-chevron-right"></i></a></li>
      <?php endif; ?>
    </ul></nav>
  </div>
</div><!-- /table-wrap -->

<?php endif; ?>
</div><!-- /container -->

<footer class="text-center">
  <div class="container">
    BTC/IDR Real-Time Trading Monitor &mdash; Mohammad Faris Al Fatih (22081010277) &mdash; Pemrograman Kripto &mdash; <?= date('Y') ?>
  </div>
</footer>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CHART.JS SCRIPTS                                            -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
const D = <?= $jsData ?>;

// ── Shared options ──────────────────────────────────────────────
const GRID  = { color: 'rgba(255,255,255,0.05)' };
const TICK  = { color: '#718096', font: { size: 10 }, maxTicksLimit: 8 };
const LEG   = { labels: { color: '#9ca3af', font: { size: 10 }, boxWidth: 10, padding: 10 } };
const noAnim = { duration: 400 };

function baseOpts(yFmt, yMin, yMax) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    animation: noAnim,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: LEG,
      tooltip: {
        backgroundColor: '#1a1f2e',
        borderColor: '#2d3748',
        borderWidth: 1,
        titleColor: '#9ca3af',
        bodyColor: '#e2e8f0',
        titleFont: { size: 10 },
        bodyFont: { size: 11 },
        callbacks: {
          label: ctx => {
            const v = ctx.parsed.y;
            if (v === null || v === undefined) return null;
            const lbl = ctx.dataset.label || '';
            return ` ${lbl}: ${yFmt ? yFmt(v) : v.toFixed(2)}`;
          }
        }
      }
    },
    scales: {
      x: { ticks: TICK, grid: GRID },
      y: {
        ticks: { ...TICK, callback: yFmt || (v => v.toFixed(2)) },
        grid: GRID,
        ...(yMin !== undefined ? { min: yMin } : {}),
        ...(yMax !== undefined ? { max: yMax } : {}),
      }
    }
  };
}

// ── Chart 1: Harga + Bollinger Bands ───────────────────────────
new Chart(document.getElementById('chartPrice'), {
  type: 'line',
  data: {
    labels: D.labels,
    datasets: [
      { label: 'Harga', data: D.price,
        borderColor: '#f7931a', borderWidth: 2, pointRadius: 0, tension: 0.3, fill: false, order: 1 },
      { label: 'BB Upper', data: D.bbU,
        borderColor: '#ef4444', borderWidth: 1, pointRadius: 0, borderDash: [5,3], fill: false, order: 2 },
      { label: 'BB Middle', data: D.bbM,
        borderColor: '#3b82f6', borderWidth: 1, pointRadius: 0, borderDash: [3,3], fill: false, order: 3 },
      { label: 'BB Lower', data: D.bbL,
        borderColor: '#22c55e', borderWidth: 1, pointRadius: 0, borderDash: [5,3],
        fill: { target: '+2', above: 'rgba(34,197,94,0.04)', below: 'rgba(34,197,94,0.04)' }, order: 4 },
    ]
  },
  options: {
    ...baseOpts(v => 'Rp ' + v.toLocaleString('id-ID', {maximumFractionDigits:0})),
    plugins: {
      ...baseOpts().plugins,
      legend: LEG,
      tooltip: {
        backgroundColor: '#1a1f2e', borderColor: '#2d3748', borderWidth: 1,
        titleColor: '#9ca3af', bodyColor: '#e2e8f0',
        titleFont: { size: 10 }, bodyFont: { size: 11 },
        callbacks: {
          label: ctx => {
            if (ctx.parsed.y === null) return null;
            return ` ${ctx.dataset.label}: Rp ${ctx.parsed.y.toLocaleString('id-ID', {maximumFractionDigits:0})}`;
          }
        }
      }
    }
  }
});

// ── Chart 2: RSI ───────────────────────────────────────────────
new Chart(document.getElementById('chartRsi'), {
  type: 'line',
  data: {
    labels: D.labels,
    datasets: [
      { label: 'RSI', data: D.rsi,
        borderColor: '#a855f7', borderWidth: 2, pointRadius: 0, tension: 0.3, fill: false },
      // Garis Overbought (70)
      { label: 'Overbought (70)', data: D.labels.map(() => 70),
        borderColor: 'rgba(239,68,68,0.5)', borderWidth: 1, borderDash: [4,4],
        pointRadius: 0, fill: false },
      // Garis Oversold (30)
      { label: 'Oversold (30)', data: D.labels.map(() => 30),
        borderColor: 'rgba(34,197,94,0.5)', borderWidth: 1, borderDash: [4,4],
        pointRadius: 0,
        fill: { target: '+1', above: 'rgba(34,197,94,0.06)', below: 'rgba(239,68,68,0.06)' } },
    ]
  },
  options: {
    ...baseOpts(v => v.toFixed(2), 0, 100),
    plugins: { ...baseOpts(null,0,100).plugins, legend: LEG }
  }
});

// ── Chart 3: MACD ──────────────────────────────────────────────
new Chart(document.getElementById('chartMacd'), {
  type: 'bar',
  data: {
    labels: D.labels,
    datasets: [
      { label: 'Histogram', data: D.macdH, type: 'bar',
        backgroundColor: D.macdH.map(v => v === null ? 'transparent' : v >= 0 ? 'rgba(34,197,94,0.6)' : 'rgba(239,68,68,0.6)'),
        borderColor:      D.macdH.map(v => v === null ? 'transparent' : v >= 0 ? '#22c55e' : '#ef4444'),
        borderWidth: 1, order: 2 },
      { label: 'MACD Line', data: D.macdL, type: 'line',
        borderColor: '#3b82f6', borderWidth: 2, pointRadius: 0, fill: false, tension: 0.3, order: 1 },
      { label: 'Signal Line', data: D.macdS, type: 'line',
        borderColor: '#f7931a', borderWidth: 1.5, pointRadius: 0, fill: false,
        borderDash: [4,3], tension: 0.3, order: 1 },
    ]
  },
  options: baseOpts(v => v.toLocaleString('id-ID', {maximumFractionDigits:0}))
});

// ── Chart 4: Stochastic ────────────────────────────────────────
new Chart(document.getElementById('chartStoch'), {
  type: 'line',
  data: {
    labels: D.labels,
    datasets: [
      { label: '%K', data: D.stochK,
        borderColor: '#eab308', borderWidth: 2, pointRadius: 0, tension: 0.3, fill: false },
      { label: '%D', data: D.stochD,
        borderColor: '#ec4899', borderWidth: 1.5, pointRadius: 0, tension: 0.3,
        borderDash: [4,3], fill: false },
      { label: 'Overbought (80)', data: D.labels.map(() => 80),
        borderColor: 'rgba(239,68,68,0.4)', borderWidth: 1, borderDash: [4,4], pointRadius: 0, fill: false },
      { label: 'Oversold (20)', data: D.labels.map(() => 20),
        borderColor: 'rgba(34,197,94,0.4)', borderWidth: 1, borderDash: [4,4], pointRadius: 0,
        fill: { target: '+1', above: 'rgba(34,197,94,0.05)', below: 'rgba(239,68,68,0.05)' } },
    ]
  },
  options: {
    ...baseOpts(v => v.toFixed(2), 0, 100),
    plugins: { ...baseOpts(null,0,100).plugins, legend: LEG }
  }
});

// ── Chart 5: Parabolic SAR vs Harga ───────────────────────────
// SAR ditampilkan sebagai scatter dots (warna sesuai tren)
const sarDots = D.sar.map((v, i) => ({
  x: D.labels[i],
  y: v,
  trend: D.sarTrend[i]
}));

new Chart(document.getElementById('chartSar'), {
  type: 'line',
  data: {
    labels: D.labels,
    datasets: [
      { label: 'Harga', data: D.price,
        borderColor: '#f7931a', borderWidth: 2, pointRadius: 0, tension: 0.3, fill: false, order: 1 },
      { label: 'SAR (UP)', data: D.sar.map((v, i) => D.sarTrend[i] === 'UP' ? v : null),
        borderColor: 'transparent', backgroundColor: '#22c55e',
        pointRadius: 3, pointStyle: 'circle', showLine: false, order: 0 },
      { label: 'SAR (DOWN)', data: D.sar.map((v, i) => D.sarTrend[i] === 'DOWN' ? v : null),
        borderColor: 'transparent', backgroundColor: '#ef4444',
        pointRadius: 3, pointStyle: 'circle', showLine: false, order: 0 },
    ]
  },
  options: {
    ...baseOpts(v => 'Rp ' + v.toLocaleString('id-ID', {maximumFractionDigits:0})),
    plugins: {
      legend: LEG,
      tooltip: {
        backgroundColor: '#1a1f2e', borderColor: '#2d3748', borderWidth: 1,
        titleColor: '#9ca3af', bodyColor: '#e2e8f0',
        titleFont: { size: 10 }, bodyFont: { size: 11 },
        callbacks: {
          label: ctx => {
            if (ctx.parsed.y === null) return null;
            return ` ${ctx.dataset.label}: Rp ${ctx.parsed.y.toLocaleString('id-ID', {maximumFractionDigits:0})}`;
          }
        }
      }
    }
  }
});

// ── Auto reload halaman tiap 5 detik ──────────────────────────
setTimeout(() => location.reload(), 5000);
</script>
</body>
</html>
