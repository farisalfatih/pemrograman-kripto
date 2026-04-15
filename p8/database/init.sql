-- ============================================================
-- init.sql — Inisialisasi Database BTC/IDR Trading System
-- Dijalankan otomatis saat container PostgreSQL pertama kali dibuat
-- ============================================================

-- Tabel ticker mentah dari Indodax
CREATE TABLE IF NOT EXISTS tickers_indodax (
    id          BIGSERIAL PRIMARY KEY,
    pair        VARCHAR(20)    NOT NULL DEFAULT 'btc_idr',
    buy         NUMERIC(20,2)  NOT NULL DEFAULT 0,
    sell        NUMERIC(20,2)  NOT NULL DEFAULT 0,
    low         NUMERIC(20,2)  NOT NULL DEFAULT 0,
    high        NUMERIC(20,2)  NOT NULL DEFAULT 0,
    last        NUMERIC(20,2)  NOT NULL DEFAULT 0,
    server_time BIGINT         NOT NULL DEFAULT 0,
    vol_coin    NUMERIC(20,8)  NOT NULL DEFAULT 0,
    vol_idr     NUMERIC(20,2)  NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tickers_pair        ON tickers_indodax (pair);
CREATE INDEX IF NOT EXISTS idx_tickers_created_at  ON tickers_indodax (created_at DESC);
CREATE INDEX IF NOT EXISTS idx_tickers_server_time ON tickers_indodax (server_time DESC);

-- Tabel indikator teknikal
CREATE TABLE IF NOT EXISTS indicators (
    id              BIGSERIAL PRIMARY KEY,
    ticker_id       BIGINT REFERENCES tickers_indodax(id) ON DELETE CASCADE,
    calculated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    -- RSI
    rsi             NUMERIC(10,4),

    -- MACD
    macd_line       NUMERIC(20,6),
    macd_signal     NUMERIC(20,6),
    macd_histogram  NUMERIC(20,6),

    -- Bollinger Bands
    bb_upper        NUMERIC(20,2),
    bb_middle       NUMERIC(20,2),
    bb_lower        NUMERIC(20,2),
    bb_width        NUMERIC(10,6),

    -- Stochastic
    stoch_k         NUMERIC(10,4),
    stoch_d         NUMERIC(10,4),

    -- Parabolic SAR
    parabolic_sar   NUMERIC(20,2),
    sar_trend       VARCHAR(4),

    -- Sinyal
    signal          VARCHAR(20),
    signal_score    SMALLINT,
    signal_detail   JSONB
);

CREATE INDEX IF NOT EXISTS idx_indicators_ticker_id     ON indicators (ticker_id);
CREATE INDEX IF NOT EXISTS idx_indicators_calculated_at ON indicators (calculated_at DESC);

-- View gabungan untuk tampilan dashboard
CREATE OR REPLACE VIEW v_ticker_with_indicators AS
SELECT
    t.id,
    t.pair,
    t.buy,
    t.sell,
    t.low,
    t.high,
    t.last,
    t.server_time,
    t.vol_coin,
    t.vol_idr,
    t.created_at,
    i.rsi,
    i.macd_line,
    i.macd_signal,
    i.macd_histogram,
    i.bb_upper,
    i.bb_middle,
    i.bb_lower,
    i.bb_width,
    i.stoch_k,
    i.stoch_d,
    i.parabolic_sar,
    i.sar_trend,
    i.signal,
    i.signal_score,
    i.signal_detail
FROM tickers_indodax t
LEFT JOIN indicators i ON i.ticker_id = t.id
ORDER BY t.created_at DESC;
