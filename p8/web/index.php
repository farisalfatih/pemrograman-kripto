<?php
// ============================================================
// index.php — Halaman Utama: Tabel Ticker + Dashboard Indikator
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Indicators.php';

// ── Koneksi & data ────────────────────────────────────────────
try {
    $db = getDB();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
    $dbErr = $e->getMessage();
}

// Pagination
$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$totalRows  = 0;
$totalPages = 1;
$rows       = [];
$latest     = null;
$summary    = ['vol_coin' => 0, 'vol_idr' => 0];

if ($dbOk) {
    // Total data
    $totalRows  = (int) $db->query("SELECT COUNT(*) FROM tickers_indodax WHERE pair='btc_idr'")->fetchColumn();
    $totalPages = max(1, ceil($totalRows / $limit));
    $page       = min($page, $totalPages);
    $offset     = ($page - 1) * $limit;

    // Data tabel halaman ini — gabung dengan indikator
    $stmt = $db->prepare("
        SELECT
            t.id, t.pair, t.buy, t.sell, t.low, t.high, t.last,
            t.server_time, t.vol_coin, t.vol_idr, t.created_at,
            i.rsi, i.macd_line, i.macd_histogram,
            i.bb_upper, i.bb_lower,
            i.stoch_k, i.stoch_d,
            i.sar_trend, i.signal, i.signal_score
        FROM tickers_indodax t
        LEFT JOIN indicators i ON i.ticker_id = t.id
        WHERE t.pair='btc_idr'
        ORDER BY t.created_at DESC
        LIMIT :lim OFFSET :off
    ");
    $stmt->execute([':lim' => $limit, ':off' => $offset]);
    $rows = $stmt->fetchAll();

    // Summary halaman ini
    $summary['vol_coin'] = array_sum(array_column($rows, 'vol_coin'));
    $summary['vol_idr']  = array_sum(array_column($rows, 'vol_idr'));

    // Data terbaru untuk header / indikator
    $latest = $db->query("SELECT * FROM v_ticker_with_indicators LIMIT 1")->fetch();
}

// ── Helper functions ──────────────────────────────────────────
function rp(float $v, int $dec = 2): string {
    return 'Rp ' . number_format($v, $dec, ',', '.');
}
function pct(float $v, int $dec = 2): string {
    return number_format($v, $dec, ',', '.') . '%';
}
function signalClass(string $sig): string {
    return match($sig) {
        'STRONG_BUY'  => 'badge-strong-buy',
        'BUY'         => 'badge-buy',
        'SELL'        => 'badge-sell',
        'STRONG_SELL' => 'badge-strong-sell',
        default       => 'badge-neutral',
    };
}
function signalLabel(string $sig): string {
    return str_replace('_', ' ', $sig);
}
function sarClass(string $t): string {
    return $t === 'UP' ? 'text-success fw-bold' : 'text-danger fw-bold';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BTC/IDR — Real-Time Trading Dashboard</title>
<meta http-equiv="refresh" content="5">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ── Base ───────────────────────────────────────── */
body { background:#0f1117; color:#e2e8f0; font-family:'Segoe UI',system-ui,sans-serif; }

/* ── Navbar ─────────────────────────────────────── */
.navbar-custom { background:#1a1f2e; border-bottom:1px solid #2d3748; padding:12px 0; }
.brand-icon { width:32px;height:32px;border-radius:50%;background:#f7931a;
              display:inline-flex;align-items:center;justify-content:center;
              font-weight:700;font-size:14px;color:#000;margin-right:8px; }
.brand-text { font-size:17px;font-weight:600;color:#f7931a; }
.brand-sub  { font-size:11px;color:#718096; }
.live-badge { background:rgba(34,197,94,.15);color:#22c55e;border:1px solid rgba(34,197,94,.3);
              padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600; }
.live-dot   { width:7px;height:7px;border-radius:50%;background:#22c55e;
              display:inline-block;margin-right:4px;animation:blink 1.5s infinite; }
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

/* ── Stat Cards ─────────────────────────────────── */
.stat-card  { background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;
              padding:16px 20px;transition:border-color .2s; }
.stat-card:hover { border-color:#4a5568; }
.stat-label { font-size:10px;color:#718096;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px; }
.stat-value { font-size:20px;font-weight:700;line-height:1; }
.stat-sub   { font-size:10px;color:#718096;margin-top:4px; }

/* ── Indicator Cards ────────────────────────────── */
.ind-card  { background:#1a1f2e;border:1px solid #2d3748;border-radius:10px;padding:14px 16px; }
.ind-title { font-size:9px;color:#718096;text-transform:uppercase;letter-spacing:.8px;margin-bottom:10px;font-weight:600; }
.ind-row   { display:flex;justify-content:space-between;align-items:center;padding:3px 0;font-size:12px; }
.ind-key   { color:#718096; }
.ind-val   { font-weight:600;font-family:monospace; }

/* Gauge */
.gauge     { height:4px;background:#2d3748;border-radius:2px;margin-top:8px;overflow:hidden; }
.gauge-fill{ height:100%;border-radius:2px;transition:width .5s; }

/* ── Signal Badges ──────────────────────────────── */
.badge-strong-buy  { background:rgba(34,197,94,.2);color:#22c55e;border:1px solid rgba(34,197,94,.4); }
.badge-buy         { background:rgba(134,239,172,.12);color:#86efac;border:1px solid rgba(134,239,172,.25); }
.badge-neutral     { background:rgba(234,179,8,.12);color:#eab308;border:1px solid rgba(234,179,8,.25); }
.badge-sell        { background:rgba(252,165,165,.12);color:#fca5a5;border:1px solid rgba(252,165,165,.25); }
.badge-strong-sell { background:rgba(239,68,68,.2);color:#ef4444;border:1px solid rgba(239,68,68,.4); }
.sig-badge { padding:3px 10px;border-radius:5px;font-size:11px;font-weight:600;white-space:nowrap; }

/* ── Table ──────────────────────────────────────── */
.table-wrap { background:#1a1f2e;border:1px solid #2d3748;border-radius:12px;overflow:hidden; }
.tbl-header { padding:14px 20px;border-bottom:1px solid #2d3748;
              display:flex;align-items:center;justify-content:space-between; }
.tbl-title  { font-size:13px;font-weight:600;color:#e2e8f0; }
.table      { --bs-table-bg: transparent;--bs-table-striped-bg: rgba(255,255,255,.025);
              --bs-table-hover-bg: rgba(255,255,255,.04);
              --bs-table-color: #e2e8f0;--bs-table-border-color: #2d3748;
              margin:0; }
.table thead th { background:#111827;color:#9ca3af;font-size:10px;text-transform:uppercase;
                   letter-spacing:.8px;font-weight:600;border-color:#2d3748;
                   padding:10px 12px;white-space:nowrap;text-align:center; }
.table tbody td { font-size:12px;padding:9px 12px;text-align:center;
                   vertical-align:middle;border-color:#2d3748;font-family:monospace; }
.table tbody tr:last-child td { border-bottom:0; }

/* Nilai warna */
.v-green { color:#22c55e; }
.v-red   { color:#ef4444; }
.v-amber { color:#eab308; }
.v-blue  { color:#60a5fa; }
.v-muted { color:#718096; }

/* ── Pagination ─────────────────────────────────── */
.pagination .page-link  { background:#1a1f2e;border-color:#2d3748;color:#9ca3af;font-size:12px; }
.pagination .page-item.active .page-link { background:#f7931a;border-color:#f7931a;color:#000;font-weight:600; }
.pagination .page-link:hover { background:#2d3748;color:#e2e8f0; }

/* ── Summary badges ─────────────────────────────── */
.sum-badge { padding:5px 14px;border-radius:6px;font-size:11px;font-weight:600; }

/* ── Section titles ─────────────────────────────── */
.section-title { font-size:11px;color:#718096;text-transform:uppercase;
                 letter-spacing:.8px;font-weight:600;margin-bottom:12px; }

/* ── Footer ─────────────────────────────────────── */
footer { border-top:1px solid #2d3748;padding:14px 0;color:#4a5568;font-size:11px; }
</style>
</head>
<body>

<!-- ── NAVBAR ────────────────────────────────────────────────── -->
<nav class="navbar-custom">
  <div class="container-fluid px-4">
    <div class="d-flex align-items-center justify-content-between">
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center">
          <span class="brand-icon">₿</span>
          <div>
            <div class="brand-text">BTC / IDR</div>
            <div class="brand-sub">Indodax · Real-Time Trading Monitor</div>
          </div>
        </div>
        <span class="live-badge"><span class="live-dot"></span>LIVE · Refresh 5s</span>
      </div>
      <div class="v-muted" style="font-size:11px;">
        <i class="bi bi-clock me-1"></i><?= date('d M Y, H:i:s') ?>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid px-4 py-3">

<?php if (!$dbOk): ?>
  <div class="alert alert-danger mt-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    Koneksi database gagal: <?= htmlspecialchars($dbErr) ?>
  </div>
<?php else: ?>

<!-- ── HARGA UTAMA ────────────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <?php
  $price    = floatval($latest['last']     ?? 0);
  $buy      = floatval($latest['buy']      ?? 0);
  $sell     = floatval($latest['sell']     ?? 0);
  $high     = floatval($latest['high']     ?? 0);
  $low      = floatval($latest['low']      ?? 0);
  $volBtc   = floatval($latest['vol_coin'] ?? 0);
  $volIdr   = floatval($latest['vol_idr']  ?? 0);
  $spread   = $sell - $buy;
  ?>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-currency-bitcoin me-1"></i>Harga Terakhir</div>
      <div class="stat-value" style="color:#f7931a;"><?= rp($price, 0) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-arrow-up-circle me-1"></i>High 24j</div>
      <div class="stat-value v-green"><?= rp($high, 0) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-arrow-down-circle me-1"></i>Low 24j</div>
      <div class="stat-value v-red"><?= rp($low, 0) ?></div>
      <div class="stat-sub">IDR</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-graph-up me-1"></i>Bid / Ask</div>
      <div class="stat-value" style="font-size:14px;">
        <span class="v-green"><?= rp($buy, 0) ?></span><br>
        <span class="v-red"><?= rp($sell, 0) ?></span>
      </div>
      <div class="stat-sub">Spread: <?= rp($spread, 0) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-box me-1"></i>Volume BTC</div>
      <div class="stat-value v-blue"><?= number_format($volBtc, 4) ?></div>
      <div class="stat-sub">BTC / 24j</div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="stat-card">
      <div class="stat-label"><i class="bi bi-database me-1"></i>Total Data DB</div>
      <div class="stat-value" style="color:#a78bfa;"><?= number_format($totalRows) ?></div>
      <div class="stat-sub">Ticker tersimpan</div>
    </div>
  </div>
</div>

<!-- ── SINYAL + INDIKATOR ─────────────────────────────────────── -->
<?php
$sig      = $latest['signal']        ?? 'N/A';
$score    = $latest['signal_score']  ?? 0;
$rsi      = floatval($latest['rsi']  ?? 0);
$macdL    = floatval($latest['macd_line']      ?? 0);
$macdH    = floatval($latest['macd_histogram'] ?? 0);
$bbU      = floatval($latest['bb_upper']  ?? 0);
$bbM      = floatval($latest['bb_middle'] ?? 0);
$bbL      = floatval($latest['bb_lower']  ?? 0);
$stochK   = floatval($latest['stoch_k']  ?? 0);
$stochD   = floatval($latest['stoch_d']  ?? 0);
$psar     = floatval($latest['parabolic_sar'] ?? 0);
$sarTrend = $latest['sar_trend'] ?? '—';
$rsiZone  = $rsi < 30 ? 'Oversold' : ($rsi > 70 ? 'Overbought' : 'Normal');
$rsiColor = $rsi < 30 ? '#22c55e' : ($rsi > 70 ? '#ef4444' : '#eab308');
?>
<div class="row g-3 mb-3">

  <!-- Sinyal Komposit -->
  <div class="col-md-3">
    <div class="ind-card h-100 d-flex flex-column justify-content-between">
      <div class="ind-title">Sinyal Komposit</div>
      <?php if ($sig !== 'N/A'): ?>
        <div class="text-center my-2">
          <span class="sig-badge <?= signalClass($sig) ?>" style="font-size:18px;padding:10px 24px;">
            <?= signalLabel($sig) ?>
          </span>
        </div>
        <div class="ind-row mt-2">
          <span class="ind-key">Score</span>
          <span class="ind-val" style="font-size:20px;color:<?= $score >= 0 ? '#22c55e' : '#ef4444' ?>">
            <?= ($score > 0 ? '+' : '') . $score ?>
          </span>
        </div>
        <div class="ind-row">
          <span class="ind-key">Akumulasi</span>
          <span class="ind-val v-muted"><?= $totalRows ?> data</span>
        </div>
      <?php else: ?>
        <div class="text-center py-3 v-muted">Akumulasi data...</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- RSI -->
  <div class="col-md col-6">
    <div class="ind-card h-100">
      <div class="ind-title">RSI (14)</div>
      <div class="ind-row"><span class="ind-key">Nilai</span>
        <span class="ind-val" style="color:<?= $rsiColor ?>"><?= $rsi ? number_format($rsi, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Zona</span>
        <span class="ind-val" style="color:<?= $rsiColor ?>"><?= $rsiZone ?></span></div>
      <div class="gauge mt-2">
        <div class="gauge-fill" style="width:<?= min(100,$rsi) ?>%;background:<?= $rsiColor ?>;"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:9px;color:#4a5568;margin-top:2px;">
        <span>0</span><span>30</span><span>70</span><span>100</span>
      </div>
    </div>
  </div>

  <!-- MACD -->
  <div class="col-md col-6">
    <div class="ind-card h-100">
      <div class="ind-title">MACD (12,26,9)</div>
      <div class="ind-row"><span class="ind-key">Line</span>
        <span class="ind-val"><?= $latest['macd_line'] ? number_format($macdL, 0, ',', '.') : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Histogram</span>
        <span class="ind-val" style="color:<?= $macdH >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= $latest['macd_histogram'] ? (($macdH > 0 ? '+' : '') . number_format($macdH, 0, ',', '.')) : '—' ?>
        </span></div>
      <div class="ind-row"><span class="ind-key">Tren</span>
        <span class="ind-val" style="color:<?= $macdH >= 0 ? '#22c55e' : '#ef4444' ?>">
          <?= $latest['macd_histogram'] ? ($macdH >= 0 ? 'Bullish' : 'Bearish') : '—' ?>
        </span></div>
    </div>
  </div>

  <!-- Bollinger Bands -->
  <div class="col-md col-6">
    <div class="ind-card h-100">
      <div class="ind-title">Bollinger Bands (20)</div>
      <div class="ind-row"><span class="ind-key">Upper</span>
        <span class="ind-val v-red"><?= $bbU ? rp($bbU, 0) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Middle</span>
        <span class="ind-val v-blue"><?= $bbM ? rp($bbM, 0) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Lower</span>
        <span class="ind-val v-green"><?= $bbL ? rp($bbL, 0) : '—' ?></span></div>
    </div>
  </div>

  <!-- Stochastic -->
  <div class="col-md col-6">
    <div class="ind-card h-100">
      <div class="ind-title">Stochastic (14,3)</div>
      <div class="ind-row"><span class="ind-key">%K</span>
        <span class="ind-val"><?= $stochK ? number_format($stochK, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">%D</span>
        <span class="ind-val"><?= $stochD ? number_format($stochD, 2) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Zona</span>
        <span class="ind-val" style="color:<?= $stochK < 20 ? '#22c55e' : ($stochK > 80 ? '#ef4444' : '#eab308') ?>">
          <?= $stochK ? ($stochK < 20 ? 'Oversold' : ($stochK > 80 ? 'Overbought' : 'Normal')) : '—' ?>
        </span></div>
      <div class="gauge mt-2">
        <div class="gauge-fill" style="width:<?= min(100,$stochK) ?>%;background:#a855f7;"></div>
      </div>
    </div>
  </div>

  <!-- Parabolic SAR -->
  <div class="col-md col-6">
    <div class="ind-card h-100">
      <div class="ind-title">Parabolic SAR</div>
      <div class="ind-row"><span class="ind-key">SAR</span>
        <span class="ind-val"><?= $psar ? rp($psar, 0) : '—' ?></span></div>
      <div class="ind-row"><span class="ind-key">Tren</span>
        <span class="ind-val <?= $sarTrend === 'UP' ? 'v-green' : ($sarTrend === 'DOWN' ? 'v-red' : 'v-muted') ?>">
          <?= $sarTrend ?>
          <?= $sarTrend === 'UP' ? '↑' : ($sarTrend === 'DOWN' ? '↓' : '') ?>
        </span></div>
      <div class="ind-row"><span class="ind-key">Posisi</span>
        <span class="ind-val" style="font-size:11px;">
          <?= $psar && $price ? ($psar < $price ? 'SAR di Bawah' : 'SAR di Atas') : '—' ?>
        </span></div>
    </div>
  </div>

</div><!-- /indikator row -->

<!-- ── TABEL DATA ─────────────────────────────────────────────── -->
<div class="table-wrap mb-3">
  <div class="tbl-header">
    <div>
      <div class="tbl-title"><i class="bi bi-table me-2"></i>Data Ticker BTC/IDR</div>
      <div style="font-size:11px;color:#718096;margin-top:2px;">
        Halaman <?= $page ?> dari <?= $totalPages ?> · <?= number_format($totalRows) ?> total data
      </div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <span class="sum-badge" style="background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.2);">
        <i class="bi bi-database me-1"></i>Total DB: <?= number_format($totalRows) ?>
      </span>
      <span class="sum-badge" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.2);">
        <i class="bi bi-coin me-1"></i>Vol BTC: <?= number_format($summary['vol_coin'], 4) ?>
      </span>
      <span class="sum-badge" style="background:rgba(234,179,8,.12);color:#eab308;border:1px solid rgba(234,179,8,.2);">
        <i class="bi bi-currency-exchange me-1"></i>Vol IDR: <?= number_format($summary['vol_idr'], 0) ?>
      </span>
    </div>
  </div>

  <!-- Pagination atas -->
  <div class="px-3 pt-3">
    <nav>
      <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap gap-1">
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
          </li>
        <?php endif; ?>
        <?php
        // Tampilkan maks 10 halaman di sekitar halaman aktif
        $pStart = max(1, $page - 4);
        $pEnd   = min($totalPages, $page + 5);
        if ($pStart > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
        for ($i = $pStart; $i <= $pEnd; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor;
        if ($pEnd < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; ?>
        <?php if ($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>

  <!-- Tabel -->
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>#</th>
          <th>Pair</th>
          <th>Buy (IDR)</th>
          <th>Sell (IDR)</th>
          <th>Low (IDR)</th>
          <th>High (IDR)</th>
          <th>Last (IDR)</th>
          <th>Waktu Server</th>
          <th>Vol BTC</th>
          <th>Vol IDR</th>
          <th>RSI</th>
          <th>MACD Hist</th>
          <th>BB Position</th>
          <th>Stoch %K</th>
          <th>SAR Tren</th>
          <th>Signal</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="16" class="text-center py-4 v-muted">
          <i class="bi bi-hourglass me-2"></i>Menunggu data dari API...
        </td></tr>
      <?php else: ?>
        <?php foreach ($rows as $i => $r):
          $rsiV   = $r['rsi']   ? floatval($r['rsi']) : null;
          $macdHV = $r['macd_histogram'] ? floatval($r['macd_histogram']) : null;
          $skV    = $r['stoch_k'] ? floatval($r['stoch_k']) : null;
          $lastV  = floatval($r['last']);
          $bbUV   = $r['bb_upper']  ? floatval($r['bb_upper'])  : null;
          $bbLV   = $r['bb_lower']  ? floatval($r['bb_lower'])  : null;
          $bbPos  = ($bbUV && $bbLV) ? ($lastV >= $bbUV ? 'AT UPPER' : ($lastV <= $bbLV ? 'AT LOWER' : 'INSIDE')) : '—';
          $bbPosColor = $bbPos === 'AT UPPER' ? '#ef4444' : ($bbPos === 'AT LOWER' ? '#22c55e' : '#eab308');
          $rowNum = $offset + $i + 1;
        ?>
        <tr>
          <td class="v-muted"><?= $rowNum ?></td>
          <td><span style="background:rgba(247,147,26,.15);color:#f7931a;
               padding:2px 8px;border-radius:4px;font-weight:600;font-size:11px;">
            <?= strtoupper(htmlspecialchars($r['pair'])) ?></span></td>
          <td class="v-green"><?= number_format($r['buy'],  0, ',', '.') ?></td>
          <td class="v-red"  ><?= number_format($r['sell'], 0, ',', '.') ?></td>
          <td><?= number_format($r['low'],  0, ',', '.') ?></td>
          <td><?= number_format($r['high'], 0, ',', '.') ?></td>
          <td style="color:#f7931a;font-weight:600;"><?= number_format($r['last'], 0, ',', '.') ?></td>
          <td class="v-muted" style="font-size:11px;"><?= date('d/m H:i:s', $r['server_time']) ?></td>
          <td class="v-blue"><?= number_format($r['vol_coin'], 4) ?></td>
          <td class="v-muted" style="font-size:11px;"><?= number_format($r['vol_idr'], 0, ',', '.') ?></td>
          <td style="color:<?= $rsiV ? ($rsiV < 30 ? '#22c55e' : ($rsiV > 70 ? '#ef4444' : '#eab308')) : '#718096' ?>">
            <?= $rsiV ? number_format($rsiV, 2) : '—' ?></td>
          <td style="color:<?= $macdHV !== null ? ($macdHV >= 0 ? '#22c55e' : '#ef4444') : '#718096' ?>">
            <?= $macdHV !== null ? (($macdHV > 0 ? '+' : '') . number_format($macdHV, 0, ',', '.')) : '—' ?></td>
          <td style="color:<?= $bbPosColor ?>;font-size:11px;"><?= $bbPos ?></td>
          <td style="color:<?= $skV ? ($skV < 20 ? '#22c55e' : ($skV > 80 ? '#ef4444' : '#eab308')) : '#718096' ?>">
            <?= $skV ? number_format($skV, 2) : '—' ?></td>
          <td>
            <?php if ($r['sar_trend']): ?>
              <span style="color:<?= $r['sar_trend'] === 'UP' ? '#22c55e' : '#ef4444' ?>;font-weight:600;">
                <?= $r['sar_trend'] ?> <?= $r['sar_trend'] === 'UP' ? '↑' : '↓' ?>
              </span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php if ($r['signal']): ?>
              <span class="sig-badge <?= signalClass($r['signal']) ?>">
                <?= signalLabel($r['signal']) ?>
              </span>
            <?php else: ?>
              <span class="v-muted" style="font-size:11px;">Akumulasi...</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination bawah -->
  <div class="px-3 pb-3 pt-2">
    <nav>
      <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap gap-1">
        <?php if ($page > 1): ?>
          <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>">
            <i class="bi bi-chevron-left"></i></a></li>
        <?php endif; ?>
        <?php for ($i = $pStart; $i <= $pEnd; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>">
            <i class="bi bi-chevron-right"></i></a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</div><!-- /table-wrap -->

<?php endif; ?>
</div><!-- /container -->

<footer class="text-center">
  <div class="container">
    BTC/IDR Real-Time Trading Monitor &mdash; Data: Indodax API &mdash;
    Refresh otomatis setiap 5 detik &mdash; <?= date('Y') ?>
  </div>
</footer>

</body>
</html>
