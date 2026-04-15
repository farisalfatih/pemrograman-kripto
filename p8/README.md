# BTC/IDR Real-Time Trading Monitor

---

## Identitas

| Atribut | Keterangan |
|---|---|
| **Nama** | Mohammad Faris Al Fatih |
| **NPM** | 22081010277 |
| **Mata Kuliah** | Pemrograman Kripto |
| **Tugas** | Ujian Tengah Semester |

---

## Deskripsi Proyek

Aplikasi web berbasis PHP untuk memantau harga **Bitcoin (BTC/IDR)** secara real-time dari API Indodax. Data diambil setiap **5 detik**, disimpan ke database **PostgreSQL**, dan ditampilkan dalam dashboard yang dilengkapi:

- Tabel data ticker dengan pagination
- Indikator teknikal: RSI, MACD, Bollinger Bands, Stochastic, Parabolic SAR
- Sinyal trading komposit otomatis (STRONG BUY → STRONG SELL)
- Seluruh layanan dijalankan dengan **Docker Compose**

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────────────┐
│                      Docker Compose                         │
│                                                             │
│  ┌──────────┐   fetch 5s   ┌──────────────────────────┐    │
│  │ Indodax  │ ──────────▶  │  fetcher (PHP CLI)        │    │
│  │   API    │              │  - Ambil ticker            │    │
│  └──────────┘              │  - Hitung RSI, MACD, BB   │    │
│                            │  - Stochastic, SAR         │    │
│                            │  - Generate sinyal         │    │
│                            └────────────┬───────────────┘    │
│                                         │ INSERT              │
│                            ┌────────────▼───────────────┐    │
│                            │     PostgreSQL              │    │
│                            │  - tickers_indodax          │    │
│                            │  - indicators               │    │
│                            └────────────┬───────────────┘    │
│                                         │ SELECT              │
│  ┌──────────┐  proxy   ┌───────────┐   │                     │
│  │ Browser  │ ───────▶ │  Nginx    │   │                     │
│  │ :8080    │          │  :80      │   │                     │
│  └──────────┘          └─────┬─────┘   │                     │
│                              │         │                     │
│                        ┌─────▼─────────▼──────┐             │
│                        │  PHP-FPM (index.php)  │             │
│                        │  - Tampilkan tabel    │             │
│                        │  - Pagination         │             │
│                        │  - Dashboard          │             │
│                        └──────────────────────┘             │
└─────────────────────────────────────────────────────────────┘
```

---

## Struktur File

```
btc_trading/
├── docker-compose.yml          ← Orkestrasi semua service
├── .env                        ← Konfigurasi environment
│
├── database/
│   └── init.sql                ← Skema tabel PostgreSQL
│
├── nginx/
│   └── default.conf            ← Konfigurasi Nginx
│
├── php/
│   ├── Dockerfile              ← Image PHP-FPM + pdo_pgsql
│   └── composer.json           ← Dependensi PHP
│
└── web/                        ← Source code aplikasi
    ├── index.php               ← Halaman utama (tabel + dashboard)
    ├── fetcher.php             ← Daemon pengambil data
    ├── Indicators.php          ← Kalkulasi indikator teknikal
    ├── api.php                 ← REST API endpoint (JSON)
    └── db.php                  ← Koneksi PDO PostgreSQL
```

---

## Cara Menjalankan

### Prasyarat

- Docker Desktop (Windows/macOS) atau Docker Engine + Docker Compose (Linux)
- Koneksi internet (untuk pull image dan akses Indodax API)

### Langkah-langkah

**1. Clone / ekstrak folder proyek**
```bash
cd btc_trading
```

**2. (Opsional) Sesuaikan konfigurasi di `.env`**
```env
POSTGRES_DB=crypto_trading
POSTGRES_USER=crypto_user
POSTGRES_PASSWORD=crypto_pass123
```

**3. Jalankan semua container**
```bash
docker compose up -d --build
```

**4. Cek status container**
```bash
docker compose ps
```
Pastikan semua service `Up`:
```
btc_postgres   Up (healthy)
btc_php        Up
btc_nginx      Up
btc_fetcher    Up
```

**5. Buka aplikasi di browser**
```
http://localhost:8080
```

**6. Pantau log fetcher (opsional)**
```bash
docker compose logs -f fetcher
```

### Menghentikan Aplikasi
```bash
docker compose down
```

Untuk menghapus data database juga:
```bash
docker compose down -v
```

---

## Indikator Teknikal

| Indikator | Parameter | Interpretasi Beli | Interpretasi Jual |
|---|---|---|---|
| **RSI** | Periode 14 | < 30 (Oversold) | > 70 (Overbought) |
| **MACD** | 12, 26, 9 | Histogram > 0 (Bullish) | Histogram < 0 (Bearish) |
| **Bollinger Bands** | 20, σ×2 | Harga ≤ Lower Band | Harga ≥ Upper Band |
| **Stochastic** | K=14, D=3 | K & D < 20 | K & D > 80 |
| **Parabolic SAR** | AF=0.02, Max=0.2 | SAR di bawah harga (Uptrend) | SAR di atas harga (Downtrend) |

### Sistem Sinyal Komposit

Setiap indikator memberikan skor. Total skor menentukan sinyal:

| Skor | Sinyal |
|---|---|
| ≥ +4 | **STRONG BUY** |
| +2 s/d +3 | **BUY** |
| -1 s/d +1 | **NEUTRAL** |
| -3 s/d -2 | **SELL** |
| ≤ -4 | **STRONG SELL** |

> **Disclaimer**: Indikator ini hanya untuk tujuan edukasi dan penelitian akademik. Bukan merupakan saran investasi. Trading aset kripto mengandung risiko tinggi.

---

## API Endpoint

| Endpoint | Deskripsi |
|---|---|
| `GET /api.php?action=latest` | Data ticker + indikator terbaru |
| `GET /api.php?action=chart&limit=100` | 100 data terakhir untuk chart |
| `GET /api.php?action=stats` | Statistik 1 jam terakhir |

---

## Teknologi yang Digunakan

| Komponen | Teknologi |
|---|---|
| Database | PostgreSQL 16 |
| Backend | PHP 8.2 (PHP-FPM) |
| Web Server | Nginx Alpine |
| Containerization | Docker Compose |
| Frontend | Bootstrap 5.3, Bootstrap Icons |
| Data Source | Indodax Public API |
