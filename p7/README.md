# 📊 Crypto Data Ingestion Pipeline (Indodax API)

## 🧑‍🎓 Informasi Tugas
- Nama: Mohammad Faris Al Fatih  
- NPM: 22081010277  
- Mata Kuliah: Pemrograman Kripto  
- Pertemuan: Ke-7  
- Topik: Data Ingestion & Storage Pipeline (Crypto Market Data)

---

## 🚀 Deskripsi Project

Project ini adalah sistem pengambilan data harga cryptocurrency dari API Indodax yang berjalan otomatis setiap 5 menit dan disimpan ke PostgreSQL menggunakan pendekatan asynchronous.

Pipeline ini mensimulasikan konsep dasar Data Engineering:

- Extract: Ambil data dari API Indodax  
- Transform: Parsing data ticker crypto  
- Load: Simpan ke PostgreSQL  
- Schedule: Jalan otomatis tiap 5 menit  

---

## 🏗️ Arsitektur Sistem

Indodax API  
→ aiohttp (async request)  
→ Python Async Worker  
→ PostgreSQL Database  
→ Docker Compose  

---

## 🧰 Teknologi

- Python 3.12  
- aiohttp  
- asyncpg  
- PostgreSQL 16  
- Docker & Docker Compose  

---

## 📦 Struktur Project
'''
project/
 ├── docker-compose.yml
 ├── db/
 │    └── schema.sql
 ├── app/
 │    ├── main.py
 │    ├── Dockerfile
 │    └── requirements.txt
 └── .env
'''
---

## 🗄️ Database Schema

Table: tickers_indodax

- id (SERIAL PRIMARY KEY)  
- pair (TEXT)  
- buy (DOUBLE PRECISION)  
- sell (DOUBLE PRECISION)  
- low (DOUBLE PRECISION)  
- high (DOUBLE PRECISION)  
- last (DOUBLE PRECISION)  
- server_time (BIGINT)  
- vol_coin (DOUBLE PRECISION)  
- vol_idr (DOUBLE PRECISION)  
- created_at (TIMESTAMP)  

---

## ⚙️ Cara Menjalankan

docker compose up --build  

docker compose up -d --build  

---

## 📊 Cara Cek Data

docker exec -it tickers_db psql -U postgres -d tickers  

\dt  

SELECT * FROM tickers_indodax LIMIT 10;  

SELECT COUNT(*) FROM tickers_indodax;  

---

## 🔁 Cara Kerja

1. Ambil data dari API Indodax  
2. Parsing ticker crypto  
3. Insert ke PostgreSQL  
4. Loop setiap 5 menit  
5. Data jadi histori harga  

---

## 🎯 Tujuan Pembelajaran

- Data ingestion pipeline  
- Async Python (aiohttp)  
- PostgreSQL integration  
- Docker deployment  
- Basic Data Engineering workflow  

---

## 📌 Kesimpulan

Project ini adalah implementasi sederhana pipeline Data Engineering untuk data cryptocurrency dengan sistem otomatis berbasis Docker dan asyncio.
