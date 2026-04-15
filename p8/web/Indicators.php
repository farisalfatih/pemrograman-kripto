<?php
// ============================================================
// Indicators.php — RSI, MACD, Bollinger Bands, Stochastic, SAR
// ============================================================

class Indicators {

    // ----------------------------------------------------------
    // RSI — Relative Strength Index (Wilder's Smoothing)
    // ----------------------------------------------------------
    public static function rsi(array $closes, int $period = 14): ?float {
        $n = count($closes);
        if ($n < $period + 1) return null;

        $gains = $losses = [];
        for ($i = 1; $i < $n; $i++) {
            $diff     = $closes[$i] - $closes[$i - 1];
            $gains[]  = $diff > 0 ? $diff : 0;
            $losses[] = $diff < 0 ? abs($diff) : 0;
        }

        $avgGain = array_sum(array_slice($gains,  0, $period)) / $period;
        $avgLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($i = $period; $i < count($gains); $i++) {
            $avgGain = ($avgGain * ($period - 1) + $gains[$i])  / $period;
            $avgLoss = ($avgLoss * ($period - 1) + $losses[$i]) / $period;
        }

        if ($avgLoss == 0) return 100.0;
        $rs = $avgGain / $avgLoss;
        return round(100 - (100 / (1 + $rs)), 4);
    }

    // ----------------------------------------------------------
    // EMA — Exponential Moving Average
    // ----------------------------------------------------------
    public static function ema(array $data, int $period): array {
        if (count($data) < $period) return [];
        $k    = 2 / ($period + 1);
        $ema  = [array_sum(array_slice($data, 0, $period)) / $period];
        $len  = count($data);
        for ($i = $period; $i < $len; $i++) {
            $ema[] = $data[$i] * $k + end($ema) * (1 - $k);
        }
        return $ema;
    }

    // ----------------------------------------------------------
    // MACD (12, 26, 9)
    // ----------------------------------------------------------
    public static function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): ?array {
        if (count($closes) < $slow + $signal) return null;

        $emaFast  = self::ema($closes, $fast);
        $emaSlow  = self::ema($closes, $slow);
        $offset   = count($emaFast) - count($emaSlow);

        $macdLine = [];
        foreach ($emaSlow as $i => $v) {
            $macdLine[] = $emaFast[$i + $offset] - $v;
        }

        $sigLine   = self::ema($macdLine, $signal);
        $lastMacd  = end($macdLine);
        $lastSig   = end($sigLine);

        return [
            'macd'      => round((float)$lastMacd, 6),
            'signal'    => round((float)$lastSig,  6),
            'histogram' => round((float)$lastMacd - (float)$lastSig, 6),
        ];
    }

    // ----------------------------------------------------------
    // Bollinger Bands (20, 2σ)
    // ----------------------------------------------------------
    public static function bollingerBands(array $closes, int $period = 20, float $mult = 2.0): ?array {
        if (count($closes) < $period) return null;
        $slice    = array_slice($closes, -$period);
        $mid      = array_sum($slice) / $period;
        $variance = array_sum(array_map(fn($v) => pow($v - $mid, 2), $slice)) / $period;
        $std      = sqrt($variance);

        return [
            'upper'  => round($mid + $mult * $std, 2),
            'middle' => round($mid, 2),
            'lower'  => round($mid - $mult * $std, 2),
            'width'  => round(($mult * 2 * $std) / $mid, 6),
        ];
    }

    // ----------------------------------------------------------
    // Stochastic Oscillator (%K, %D)
    // ----------------------------------------------------------
    public static function stochastic(array $highs, array $lows, array $closes, int $kPer = 14, int $dPer = 3): ?array {
        $n = count($closes);
        if ($n < $kPer + $dPer) return null;

        $kArr = [];
        for ($i = $kPer - 1; $i < $n; $i++) {
            $hSlice = array_slice($highs, $i - $kPer + 1, $kPer);
            $lSlice = array_slice($lows,  $i - $kPer + 1, $kPer);
            $range  = max($hSlice) - min($lSlice);
            $kArr[] = $range == 0 ? 50 : (($closes[$i] - min($lSlice)) / $range) * 100;
        }

        $dArr = [];
        for ($i = $dPer - 1; $i < count($kArr); $i++) {
            $dArr[] = array_sum(array_slice($kArr, $i - $dPer + 1, $dPer)) / $dPer;
        }

        return [
            'k' => round((float)end($kArr), 4),
            'd' => round((float)end($dArr), 4),
        ];
    }

    // ----------------------------------------------------------
    // Parabolic SAR
    // ----------------------------------------------------------
    public static function parabolicSAR(array $highs, array $lows, float $afStep = 0.02, float $afMax = 0.2): ?array {
        $n = count($highs);
        if ($n < 3) return null;

        $trend = 1;
        $sar   = $lows[0];
        $ep    = $highs[0];
        $af    = $afStep;

        for ($i = 1; $i < $n; $i++) {
            $sar += $af * ($ep - $sar);

            if ($trend == 1) {
                $sar = min($sar, $lows[$i - 1], $i >= 2 ? $lows[$i - 2] : $lows[$i - 1]);
                if ($lows[$i] < $sar) {
                    $trend = -1; $sar = $ep; $ep = $lows[$i]; $af = $afStep;
                } elseif ($highs[$i] > $ep) {
                    $ep = $highs[$i];
                    $af = min($af + $afStep, $afMax);
                }
            } else {
                $sar = max($sar, $highs[$i - 1], $i >= 2 ? $highs[$i - 2] : $highs[$i - 1]);
                if ($highs[$i] > $sar) {
                    $trend = 1; $sar = $ep; $ep = $highs[$i]; $af = $afStep;
                } elseif ($lows[$i] < $ep) {
                    $ep = $lows[$i];
                    $af = min($af + $afStep, $afMax);
                }
            }
        }

        return [
            'sar'   => round($sar, 2),
            'trend' => $trend == 1 ? 'UP' : 'DOWN',
        ];
    }

    // ----------------------------------------------------------
    // Generate Trading Signal
    // ----------------------------------------------------------
    public static function generateSignal(float $price, ?float $rsi, ?array $macd, ?array $bb, ?array $stoch, ?array $sar): array {
        $score  = 0;
        $detail = [];

        if ($rsi !== null) {
            if ($rsi < 30)     { $score += 2; $detail['rsi'] = ['sig' => 'OVERSOLD',   'val' => $rsi]; }
            elseif ($rsi < 45) { $score += 1; $detail['rsi'] = ['sig' => 'WEAK_BUY',   'val' => $rsi]; }
            elseif ($rsi > 70) { $score -= 2; $detail['rsi'] = ['sig' => 'OVERBOUGHT', 'val' => $rsi]; }
            elseif ($rsi > 55) { $score -= 1; $detail['rsi'] = ['sig' => 'WEAK_SELL',  'val' => $rsi]; }
            else               {              $detail['rsi'] = ['sig' => 'NEUTRAL',     'val' => $rsi]; }
        }

        if ($macd !== null) {
            if ($macd['histogram'] > 0) { $score += 1; $detail['macd'] = ['sig' => 'BULLISH', 'hist' => $macd['histogram']]; }
            else                         { $score -= 1; $detail['macd'] = ['sig' => 'BEARISH', 'hist' => $macd['histogram']]; }
        }

        if ($bb !== null) {
            if ($price <= $bb['lower'])      { $score += 1; $detail['bb'] = ['sig' => 'AT_LOWER']; }
            elseif ($price >= $bb['upper'])  { $score -= 1; $detail['bb'] = ['sig' => 'AT_UPPER']; }
            else                              {              $detail['bb'] = ['sig' => 'INSIDE'];   }
        }

        if ($stoch !== null) {
            if ($stoch['k'] < 20 && $stoch['d'] < 20)     { $score += 1; $detail['stoch'] = ['sig' => 'OVERSOLD'];   }
            elseif ($stoch['k'] > 80 && $stoch['d'] > 80) { $score -= 1; $detail['stoch'] = ['sig' => 'OVERBOUGHT']; }
            else                                            {              $detail['stoch'] = ['sig' => 'NEUTRAL'];    }
        }

        if ($sar !== null) {
            if ($sar['trend'] == 'UP') { $score += 1; $detail['sar'] = ['sig' => 'UPTREND'];   }
            else                        { $score -= 1; $detail['sar'] = ['sig' => 'DOWNTREND']; }
        }

        if ($score >= 4)      $label = 'STRONG_BUY';
        elseif ($score >= 2)  $label = 'BUY';
        elseif ($score <= -4) $label = 'STRONG_SELL';
        elseif ($score <= -2) $label = 'SELL';
        else                  $label = 'NEUTRAL';

        return ['signal' => $label, 'score' => $score, 'detail' => $detail];
    }
}
