"""n8n environment helpers — separate webhook paths and workflow names per CE_ENV."""
from __future__ import annotations

import os
import re
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parent.parent

ENV_LABEL_SUFFIX = {
    "production": "",
    "local": " [local]",
    "staging": " [staging]",
}

# Base webhook path → .env key (used by setup-staging-n8n.sh)
WEBHOOK_ENV_KEYS: list[tuple[str, str]] = [
    ("ce01-theme-director", "N8N_CE01_WEBHOOK_URL"),
    ("ce01b-theme-to-slot", "N8N_CE01B_WEBHOOK_URL"),
    ("ce02-research", "N8N_CE02_WEBHOOK_URL"),
    ("ce03-write-article", "N8N_CE03_WEBHOOK_URL"),
    ("ce04-review", "N8N_CE04_WEBHOOK_URL"),
    ("ce04-human-review", "N8N_CE04_HUMAN_WEBHOOK_URL"),
    ("ce07-performance-report", "N8N_CE07_WEBHOOK_URL"),
    ("ce-llm-visibility", "N8N_CE_LLM_VISIBILITY_WEBHOOK_URL"),
]


def parse_env_value(raw: str) -> str:
    raw = raw.strip()
    if not raw:
        return ""
    if raw[0] in "\"'":
        quote, i, out = raw[0], 1, []
        while i < len(raw):
            if raw[i] == "\\" and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == quote:
                break
            out.append(raw[i])
            i += 1
        return "".join(out)
    if " #" in raw:
        raw = raw.split(" #", 1)[0]
    return raw.strip()


def target_env() -> str:
    return (os.environ.get("CE_N8N_TARGET") or os.environ.get("CE_ENV") or "production").strip().lower()


def env_file_path(env: str | None = None) -> Path:
    env_name = (env or target_env()).strip().lower()
    if env_name == "production":
        prod = ROOT / ".env.production"
        return prod if prod.is_file() else ROOT / ".env"
    if env_name in ("", "local"):
        return ROOT / ".env"
    return ROOT / f".env.{env_name}"


def load_env(env: str | None = None, *, override: bool = False) -> Path:
    path = env_file_path(env)
    if not path.is_file():
        raise SystemExit(f"Missing env file: {path}")

    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        if not key:
            continue
        if not override and key in os.environ:
            continue
        os.environ[key] = parse_env_value(val)

    if env:
        os.environ["CE_N8N_TARGET"] = env.strip().lower()
    return path


def webhook_suffix() -> str:
    explicit = os.environ.get("N8N_WEBHOOK_SUFFIX")
    if explicit is not None and explicit.strip() != "":
        return explicit.strip()
    env = target_env()
    if env == "staging":
        return "-staging"
    if env == "local":
        return "-local"
    return ""


def workflow_name_label() -> str:
    env = target_env()
    if env == "production":
        return ""
    return ENV_LABEL_SUFFIX.get(env, f" [{env}]")


def strip_path_suffix(path: str) -> str:
    clean = path.strip("/")
    for suffix in ("-staging", "-local"):
        if clean.endswith(suffix):
            return clean[: -len(suffix)]
    return clean


def webhook_path(base: str) -> str:
    clean = strip_path_suffix(base.strip("/"))
    suffix = webhook_suffix()
    if suffix and not clean.endswith(suffix):
        return f"{clean}{suffix}"
    return clean


def webhook_url(base: str) -> str:
    api = os.environ.get("N8N_API_URL", "").rstrip("/")
    path = webhook_path(base)
    if not api:
        return f"/webhook/{path}"
    return f"{api}/webhook/{path}"


def strip_env_label(name: str) -> str:
    return re.sub(r" \[(staging|local)\]$", "", name or "")


def _rewrite_webhook_url(url: str, api_base: str) -> str:
    match = re.search(r"(https?://[^/\s\"']+)/webhook/([^?\s\"']+)", url)
    if not match:
        return url
    base_path = match.group(2)
    query = ""
    if "?" in url:
        query = "?" + url.split("?", 1)[1]
    host = api_base or match.group(1)
    return f"{host}/webhook/{webhook_path(base_path)}{query}"


def _rewrite_webhook_urls_in_code(code: str, api_base: str) -> str:
    return re.sub(
        r"https?://[^/\s\"']+/webhook/[^`\s\"']+",
        lambda match: _rewrite_webhook_url(match.group(0), api_base),
        code,
    )


def patch_workflow_for_env(workflow: dict[str, Any]) -> dict[str, Any]:
    """Apply workflow name label and webhook path suffix for non-production targets."""
    label = workflow_name_label()
    base_name = strip_env_label(workflow.get("name", ""))
    workflow["name"] = base_name + label if label else base_name

    api_base = os.environ.get("N8N_API_URL", "").rstrip("/")

    for node in workflow.get("nodes", []):
        node_type = node.get("type", "")
        params = node.setdefault("parameters", {})

        if node_type == "n8n-nodes-base.webhook":
            path = params.get("path", "")
            if path:
                params["path"] = webhook_path(str(path))
            webhook_id = node.get("webhookId")
            if webhook_id:
                clean = str(webhook_id).replace("-webhook", "")
                node["webhookId"] = f"{webhook_path(clean)}-webhook"

        elif node_type == "n8n-nodes-base.httpRequest":
            url = params.get("url", "")
            if isinstance(url, str) and "/webhook/" in url:
                params["url"] = _rewrite_webhook_url(url, api_base)

        elif node_type == "n8n-nodes-base.code":
            code = params.get("jsCode", "")
            if isinstance(code, str) and "/webhook/" in code:
                params["jsCode"] = _rewrite_webhook_urls_in_code(code, api_base)

        elif node_type == "n8n-nodes-base.mySql":
            cred_id = os.environ.get("N8N_MYSQL_CREDENTIAL_ID", "").strip()
            cred_name = os.environ.get("N8N_MYSQL_CREDENTIAL_NAME", "").strip()
            if cred_id:
                node["credentials"] = {
                    "mySql": {
                        "id": cred_id,
                        "name": cred_name or "Content Engine [staging]",
                    }
                }

    return workflow


def print_webhook_env_lines() -> None:
    for base_path, key in WEBHOOK_ENV_KEYS:
        print(f"{key}={webhook_url(base_path)}")
