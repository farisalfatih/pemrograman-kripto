import asyncio
import aiohttp
import asyncpg
import os

API_URL = "https://indodax.com/api/tickers"


async def fetch_data(session):
    async with session.get(API_URL, timeout=30) as resp:
        data = await resp.json()

    result = []

    for pair, v in data["tickers"].items():
        coin = pair.split("_")[0]
        vol_coin_key = f"vol_{coin}"

        result.append((
            pair,
            float(v.get("buy", 0)),
            float(v.get("sell", 0)),
            float(v.get("low", 0)),
            float(v.get("high", 0)),
            float(v.get("last", 0)),
            int(v.get("server_time", 0)),
            float(v.get(vol_coin_key, 0)),
            float(v.get("vol_idr", 0))
        ))

    return result


async def insert_data(pool, data):
    query = """
        INSERT INTO tickers_indodax
        (pair, buy, sell, low, high, last, server_time, vol_coin, vol_idr)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
    """

    async with pool.acquire() as conn:
        await conn.executemany(query, data)


async def job(pool, session):
    try:
        data = await fetch_data(session)
        await insert_data(pool, data)
        print("insert success:", len(data))
    except Exception as e:
        print("error:", e)


async def main():
    pool = await asyncpg.create_pool(
        host=os.environ["PG_HOST"],
        port=os.environ["PG_PORT"],
        user=os.environ["PG_USER"],
        password=os.environ["PG_PASSWORD"],
        database=os.environ["PG_DB"],
        min_size=1,
        max_size=5
    )

    async with aiohttp.ClientSession() as session:
        while True:
            await job(pool, session)
            await asyncio.sleep(300)

    await pool.close()


asyncio.run(main())
