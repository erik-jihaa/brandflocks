#!/usr/bin/env python3
"""Copy one tenant (all rows) from production MySQL to staging."""
from __future__ import annotations

import argparse
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def parse_env(path: Path) -> dict[str, str]:
    vals: dict[str, str] = {}
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        value = value.strip().strip('"').strip("'")
        if " #" in value:
            value = value.split(" #", 1)[0].strip()
        vals[key] = value
    return vals


def connect(env: dict[str, str]):
    import pymysql

    return pymysql.connect(
        host=env.get("DB_HOST", "localhost"),
        port=int(env.get("DB_PORT", "3306")),
        user=env["DB_USER"],
        password=env.get("DB_PASS", ""),
        database=env["DB_NAME"],
        charset="utf8mb4",
        autocommit=False,
        connect_timeout=30,
    )


def table_exists(cur, table: str) -> bool:
    cur.execute("SHOW TABLES LIKE %s", (table,))
    return cur.fetchone() is not None


def table_columns(cur, table: str) -> list[str]:
    cur.execute(f"SHOW COLUMNS FROM `{table}`")
    return [row[0] for row in cur.fetchall()]


def fetch_rows(cur, table: str, where: str, params: tuple) -> list[dict]:
    cur.execute(f"SELECT * FROM `{table}` WHERE {where}", params)
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, row)) for row in cur.fetchall()]


def delete_where(cur, table: str, where: str, params: tuple) -> int:
    cur.execute(f"DELETE FROM `{table}` WHERE {where}", params)
    return cur.rowcount


def insert_row(cur, table: str, row: dict) -> None:
    cols = list(row.keys())
    placeholders = ", ".join(["%s"] * len(cols))
    col_sql = ", ".join(f"`{c}`" for c in cols)
    cur.execute(
        f"INSERT INTO `{table}` ({col_sql}) VALUES ({placeholders})",
        [row[c] for c in cols],
    )


def copy_tenant(source, target, client_id: str) -> None:
    src = connect(source)
    dst = connect(target)
    try:
        src_cur = src.cursor()
        dst_cur = dst.cursor()
        dst_cur.execute("SET FOREIGN_KEY_CHECKS=0")

        # Remove existing staging copy of this tenant.
        if table_exists(dst_cur, "clients"):
            dst_cur.execute("SELECT id FROM clients WHERE id = %s LIMIT 1", (client_id,))
            if dst_cur.fetchone():
                slot_rows = (
                    fetch_rows(dst_cur, "content_slots", "client_id = %s", (client_id,))
                    if table_exists(dst_cur, "content_slots")
                    else []
                )
                slot_ids = [str(r["id"]) for r in slot_rows if r.get("id")]
                article_ids: list[str] = []
                if slot_ids and table_exists(dst_cur, "articles"):
                    ph = ", ".join(["%s"] * len(slot_ids))
                    dst_cur.execute(f"SELECT id FROM articles WHERE slot_id IN ({ph})", slot_ids)
                    article_ids = [str(r[0]) for r in dst_cur.fetchall()]

                for table, col, ids in [
                    ("hba_scores", "post_id", article_ids),
                    ("article_review_edits", "article_id", article_ids),
                    ("article_feedback", "article_id", article_ids),
                ]:
                    if ids and table_exists(dst_cur, table) and col in table_columns(dst_cur, table):
                        ph = ", ".join(["%s"] * len(ids))
                        delete_where(dst_cur, table, f"`{col}` IN ({ph})", tuple(ids))

                if slot_ids and table_exists(dst_cur, "articles"):
                    ph = ", ".join(["%s"] * len(slot_ids))
                    delete_where(dst_cur, "articles", f"slot_id IN ({ph})", tuple(slot_ids))
                if table_exists(dst_cur, "research_items"):
                    delete_where(dst_cur, "research_items", "client_id = %s", (client_id,))
                if slot_ids and table_exists(dst_cur, "research_items"):
                    ph = ", ".join(["%s"] * len(slot_ids))
                    delete_where(dst_cur, "research_items", f"slot_id IN ({ph})", tuple(slot_ids))
                for table in [
                    "content_slots",
                    "themes",
                    "sources",
                    "objectives",
                    "brand_narratives",
                    "client_images",
                    "linkedin_posts",
                    "performance_reports",
                    "scrape_runs",
                    "editorial_preferences",
                    "llm_visibility_questions",
                    "llm_visibility_checks",
                    "tenant_activity_daily",
                    "client_alert_settings",
                ]:
                    if table_exists(dst_cur, table) and "client_id" in table_columns(dst_cur, table):
                        delete_where(dst_cur, table, "client_id = %s", (client_id,))
                delete_where(dst_cur, "clients", "id = %s", (client_id,))

        # Direct client_id tables
        copied: dict[str, int] = {}
        client_tables = [
            "clients",
            "brand_narratives",
            "objectives",
            "editorial_preferences",
            "sources",
            "themes",
            "content_slots",
            "client_images",
            "linkedin_posts",
            "performance_reports",
            "scrape_runs",
            "llm_visibility_questions",
            "llm_visibility_checks",
            "tenant_activity_daily",
            "client_alert_settings",
        ]
        for table in client_tables:
            if not table_exists(src_cur, table) or not table_exists(dst_cur, table):
                continue
            if table == "clients":
                rows = fetch_rows(src_cur, table, "id = %s", (client_id,))
            elif "client_id" not in table_columns(src_cur, table):
                continue
            else:
                rows = fetch_rows(src_cur, table, "client_id = %s", (client_id,))
            for row in rows:
                insert_row(dst_cur, table, row)
            copied[table] = len(rows)

        slot_ids = [r["id"] for r in fetch_rows(src_cur, "content_slots", "client_id = %s", (client_id,))]
        theme_ids = [r["id"] for r in fetch_rows(src_cur, "themes", "client_id = %s", (client_id,))]

        if slot_ids and table_exists(src_cur, "articles"):
            ph = ", ".join(["%s"] * len(slot_ids))
            article_rows = fetch_rows(src_cur, "articles", f"slot_id IN ({ph})", tuple(slot_ids))
            for row in article_rows:
                insert_row(dst_cur, "articles", row)
            copied["articles"] = len(article_rows)
            article_ids = [r["id"] for r in article_rows]

            for table, col in [
                ("article_feedback", "article_id"),
                ("article_review_edits", "article_id"),
                ("hba_scores", "post_id"),
            ]:
                if not table_exists(src_cur, table) or col not in table_columns(src_cur, table):
                    continue
                ph = ", ".join(["%s"] * len(article_ids))
                rows = fetch_rows(src_cur, table, f"`{col}` IN ({ph})", tuple(article_ids))
                for row in rows:
                    insert_row(dst_cur, table, row)
                copied[table] = len(rows)

        if table_exists(src_cur, "research_items"):
            rows = fetch_rows(src_cur, "research_items", "client_id = %s", (client_id,))
            if slot_ids:
                ph = ", ".join(["%s"] * len(slot_ids))
                rows += fetch_rows(src_cur, "research_items", f"slot_id IN ({ph})", tuple(slot_ids))
            if theme_ids:
                ph = ", ".join(["%s"] * len(theme_ids))
                rows += fetch_rows(src_cur, "research_items", f"theme_id IN ({ph})", tuple(theme_ids))
            seen: set[str] = set()
            unique_rows = []
            for row in rows:
                rid = str(row.get("id", ""))
                if rid and rid not in seen:
                    seen.add(rid)
                    unique_rows.append(row)
            for row in unique_rows:
                insert_row(dst_cur, "research_items", row)
            copied["research_items"] = len(unique_rows)

        dst_cur.execute("SET FOREIGN_KEY_CHECKS=1")
        dst.commit()
        print(f"Copied tenant {client_id}:")
        for table, count in sorted(copied.items()):
            print(f"  {table}: {count}")
    except Exception:
        dst.rollback()
        raise
    finally:
        src.close()
        dst.close()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--client-id", default="016384af-b26e-4415-aa9c-abc46b804798")
    parser.add_argument("--source-env", default="production")
    parser.add_argument("--target-env", default="staging")
    args = parser.parse_args()

    source = parse_env(ROOT / f".env.{args.source_env}")
    target = parse_env(ROOT / f".env.{args.target_env}")
    copy_tenant(source, target, args.client_id)


if __name__ == "__main__":
    try:
        import pymysql  # noqa: F401
    except ImportError:
        print("Install pymysql in a venv first", file=sys.stderr)
        sys.exit(1)
    main()
