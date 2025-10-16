import reflex as rx
import os

config = rx.Config(
    app_name="electivoia",
    db_url = os.getenv("DATABASE_URL"),
    plugins=[
        rx.plugins.SitemapPlugin(),
        rx.plugins.TailwindV4Plugin(),
    ]
)