# test_db.py
import os
from dotenv import load_dotenv
import psycopg2

load_dotenv()

try:
    conn = psycopg2.connect(os.getenv("DATABASE_URL"))
    print("✅ Conexión exitosa a PostgreSQL desde Python!")
    conn.close()
except Exception as e:
    print("❌ Error:", e)