#!/usr/bin/env bash
# Stap 3 — inventaris dode code (leest alleen, wijzigt niets).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/docs/inventaris-dode-code.md}"
mkdir -p "$(dirname "$OUT")"

export ROOT OUT

python3 <<'PY'
from __future__ import annotations

import os
import re
import subprocess
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(os.environ["ROOT"])
OUT = Path(os.environ["OUT"])

SKIP_DIRS = {
    "node_modules",
    "vendor",
    ".git",
    "dist",
    "assets",
    "__pycache__",
}

TEXT_GLOBS = (
    "frontend/src",
    "scripts",
    "briefing",
    "docs",
    "web/api",
    "web/migrations",
    "linkedin-analyse",
    "tests",
)


def iter_text_files() -> list[Path]:
    files: list[Path] = []
    for rel in TEXT_GLOBS:
        base = ROOT / rel
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if not path.is_file():
                continue
            if any(part in SKIP_DIRS for part in path.parts):
                continue
            if path.suffix.lower() in {
                ".php",
                ".ts",
                ".tsx",
                ".js",
                ".jsx",
                ".md",
                ".json",
                ".sh",
                ".sql",
                ".py",
            }:
                files.append(path)
    return files


def read(path: Path) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return ""


def rg_count(pattern: str, paths: list[Path]) -> int:
    if not paths:
        return 0
    try:
        proc = subprocess.run(
            ["rg", "-l", pattern, *[str(p) for p in paths]],
            cwd=ROOT,
            capture_output=True,
            text=True,
        )
        if proc.returncode not in (0, 1):
            return -1
        lines = [ln for ln in proc.stdout.splitlines() if ln.strip()]
        return len(lines)
    except FileNotFoundError:
        corpus = "\n".join(read(p) for p in paths)
        return len(re.findall(pattern, corpus))


# --- 1. API-routes ---
index_php = ROOT / "web/api/index.php"
index_src = read(index_php)
route_paths = sorted(set(re.findall(r"\$path === '(/[^']+)'", index_src)))
runtime_roots = [ROOT / "frontend/src", ROOT / "scripts", ROOT / "linkedin-analyse"]
extra_json = ROOT.parent / "json" / "n8n"
if extra_json.is_dir():
    runtime_roots.append(extra_json)

doc_roots = runtime_roots + [ROOT / "briefing", ROOT / "docs", ROOT / "tests"]


def route_referenced(route: str, roots: list[Path]) -> bool:
    slug = route.rstrip("/").split("/")[-1]
    for base in roots:
        if not base.exists():
            continue
        for path in base.rglob("*"):
            if path == index_php:
                continue
            if not path.is_file():
                continue
            if "vendor" in path.parts or "node_modules" in path.parts:
                continue
            if path.suffix.lower() not in {".php", ".ts", ".tsx", ".js", ".json", ".md", ".sh"}:
                continue
            text = read(path)
            if route in text or slug in text:
                return True
    return False


unused_routes_runtime: list[str] = []
unused_routes_docs_only: list[str] = []
for route in route_paths:
    in_runtime = route_referenced(route, runtime_roots)
    in_docs = route_referenced(route, doc_roots)
    if not in_runtime:
        unused_routes_runtime.append(route)
    elif not in_docs and route.startswith("/admin/"):
        unused_routes_docs_only.append(route)

# --- 2. PHP functions (web/api, ce_* / hba_* / admin helpers) ---
php_files = [p for p in (ROOT / "web/api").rglob("*.php") if "vendor" not in p.parts]
func_defs: dict[str, Path] = {}
for path in php_files:
    for m in re.finditer(r"^function\s+([a-zA-Z0-9_]+)\s*\(", read(path), re.M):
        name = m.group(1)
        if name.startswith(("ce_", "hba_", "li_", "ce03_", "ce04_")) or "admin" in path.name:
            func_defs.setdefault(name, path)

unused_funcs: list[tuple[str, str]] = []
for name, def_path in sorted(func_defs.items()):
    count = 0
    for path in php_files:
        count += len(re.findall(r"\b" + re.escape(name) + r"\s*\(", read(path)))
    for path in (ROOT / "scripts").rglob("*.php"):
        count += len(re.findall(r"\b" + re.escape(name) + r"\s*\(", read(path)))
    if count <= 1:
        unused_funcs.append((name, def_path.relative_to(ROOT).as_posix()))

# --- 3. Frontend orphans ---
fe_root = ROOT / "frontend/src"
pages = sorted((fe_root / "pages").rglob("*.tsx"))
components = sorted((fe_root / "components").rglob("*.tsx"))
fe_corpus = "\n".join(read(p) for p in fe_root.rglob("*") if p.suffix in {".ts", ".tsx"})

orphan_ui: list[str] = []
for path in pages + components:
    if path.name in {"App.tsx"}:
        continue
    stem = path.stem
    # default export name often matches file
    if stem == "index":
        continue
    imports = fe_corpus.count(f"/{stem}") + fe_corpus.count(f"'./{stem}'") + fe_corpus.count(
        f'"{stem}"'
    )
    if path.name == f"{stem}.tsx":
        if fe_corpus.count(stem) <= 2:  # definition file + maybe self
            orphan_ui.append(path.relative_to(ROOT).as_posix())

# stricter: not referenced from App.tsx chain
app_src = read(fe_root / "App.tsx")
for path in pages:
    stem = path.stem
    if stem.endswith("View") or stem.endswith("Page"):
        short = stem
    else:
        short = stem
    if short not in app_src and fe_corpus.count(stem) < 3:
        rel = path.relative_to(ROOT).as_posix()
        if rel not in orphan_ui:
            orphan_ui.append(rel)
orphan_ui = sorted(set(orphan_ui))

# --- 4. DB tables ---
tables: set[str] = set()
for sql_path in (ROOT / "web/migrations").glob("*.sql"):
    for m in re.finditer(
        r"CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?",
        read(sql_path),
        re.I,
    ):
        tables.add(m.group(1).lower())

schema = ROOT / "web/schema"
if schema.is_dir():
    for sql_path in schema.rglob("*.sql"):
        for m in re.finditer(
            r"CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?([a-zA-Z0-9_]+)`?",
            read(sql_path),
            re.I,
        ):
            tables.add(m.group(1).lower())

php_corpus = "\n".join(read(p) for p in php_files)
unused_tables: list[str] = []
for table in sorted(tables):
    if table in {"schema_migrations"}:
        continue
    if table not in php_corpus.lower() and table.replace("_", "") not in php_corpus.lower():
        unused_tables.append(table)

lines: list[str] = []
lines.append("# Inventaris dode code (automatisch)")
lines.append("")
lines.append(f"Gegenereerd: {datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M UTC')}")
lines.append(f"Bron: `{ROOT.name}` — `./scripts/inventaris-dode-code.sh`")
lines.append("")
lines.append("> Handmatig aanvullen: crontab/n8n op CE-server (zie onder). False positives mogelijk — altijd dubbelchecken vóór schrappen.")
lines.append("")

lines.append("## 1. API-routes — runtime (frontend/scripts/n8n JSON)")
lines.append("")
lines.append(
    f"Totaal routes in `web/api/index.php`: **{len(route_paths)}**. "
    f"Geen runtime-referentie: **{len(unused_routes_runtime)}** (kandidaat ronde B)."
)
lines.append("")
for route in sorted(unused_routes_runtime):
    lines.append(f"- `{route}`")
if not unused_routes_runtime:
    lines.append("- *(geen)*")
lines.append("")
lines.append("Zoek ook in **`json/n8n/`** (n8n) vóór je een admin-route verwijdert.")
lines.append("")

lines.append("## 2. PHP-functies met ≤1 referentie (definitie only)")
lines.append("")
lines.append(f"Gecontroleerd: **{len(func_defs)}** functies (`ce_*`, `hba_*`, … in `web/api/`).")
lines.append("")
for name, path in unused_funcs[:120]:
    lines.append(f"- `{name}` — `{path}`")
if len(unused_funcs) > 120:
    lines.append(f"- … en **{len(unused_funcs) - 120}** meer")
if not unused_funcs:
    lines.append("- *(geen)*")
lines.append("")

lines.append("## 3. Frontend-pagina’s/componenten (weinig of geen import)")
lines.append("")
lines.append("Heuristiek — controleer handmatig (lazy routes, dynamic import).")
lines.append("")
for item in orphan_ui:
    lines.append(f"- `{item}`")
if not orphan_ui:
    lines.append("- *(geen)*")
lines.append("")

lines.append("## 4. DB-tabellen niet in PHP-code (migrations)")
lines.append("")
for table in unused_tables:
    lines.append(f"- `{table}`")
if not unused_tables:
    lines.append("- *(geen)*")
lines.append("")

lines.append("## 5. Server-check (Erik) — cron/n8n op hosting")
lines.append("")
lines.append("Operationele allowlist: `docs/operational-admin-routes.md`.")
lines.append("")
lines.append("**In repo gevonden** (scripts/`cron-daily.sh`/`generate-*-workflow.py`):")
lines.append("")
repo_admin_refs: dict[str, list[str]] = defaultdict(list)
ref_pat = re.compile(r"/admin/([a-z0-9-]+)")
for rel in ("scripts", "docs"):
    base = ROOT / rel
    if not base.exists():
        continue
    for path in base.rglob("*"):
        if not path.is_file() or path.suffix in {".png", ".jpg", ".gif"}:
            continue
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            continue
        for m in ref_pat.finditer(text):
            ep = m.group(1)
            rel_path = str(path.relative_to(ROOT))
            if rel_path not in repo_admin_refs[ep]:
                repo_admin_refs[ep].append(rel_path)
for ep in sorted(repo_admin_refs):
    refs = ", ".join(f"`{r}`" for r in repo_admin_refs[ep][:3])
    extra = f" (+{len(repo_admin_refs[ep]) - 3})" if len(repo_admin_refs[ep]) > 3 else ""
    lines.append(f"- `/admin/{ep}` — {refs}{extra}")
if not repo_admin_refs:
    lines.append("- *(geen)*")
lines.append("")
lines.append("**Alleen op server** (vul handmatig als endpoint weg mag):")
lines.append("")
lines.append("| Endpoint | Server cron/n8n | Notities |")
lines.append("|----------|-----------------|----------|")
for ep in [
    "mailbox-sync",
    "analytics-sync",
    "scraper-sync",
    "llm-visibility-sync",
    "poll-n8n-failures",
    "import-support-emails",
    "ce02-trigger",
    "hygiene",
    "theme-refill",
]:
    in_repo = "repo" if ep in repo_admin_refs else "—"
    lines.append(f"| `/admin/{ep}` | ☐ | script-ref: {in_repo} |")
lines.append("")

OUT.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Wrote {OUT}")
print(f"Routes: {len(unused_routes_runtime)} no runtime ref / {len(route_paths)} total")
print(f"PHP funcs: {len(unused_funcs)} single-ref")
print(f"UI orphans: {len(orphan_ui)}")
print(f"DB tables: {len(unused_tables)} not in PHP grep")
PY

echo "Report: $OUT"
