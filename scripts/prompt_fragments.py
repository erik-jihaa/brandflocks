"""Gedeelde promptfragmenten — één bron, hergebruikt in workflow-generators."""
from __future__ import annotations

from pathlib import Path

_ROOT = Path(__file__).resolve().parent.parent
_BLOCK_PATH = _ROOT / "web/api/prompts/nl-avoid-calques-block.txt"
_REGELBREUK_PATH = _ROOT / "web/api/prompts/hba-regelbreuk-definitie.txt"
_REGEL_LETTERLIJK_PATH = _ROOT / "web/api/prompts/hba-regel-letterlijk.txt"
_MERKBEWERING_PATH = _ROOT / "web/api/prompts/hba-merkbewering-definitie.txt"
_FLAGS_PATH = _ROOT / "web/api/prompts/hba-flags-definitie.txt"
NL_CALQUES_MARKER = "NEDERLANDS — VERMIJD CALQUE-WOORDEN"


def _js_string_literal(text: str) -> str:
    """Geëscaped voor embedding in n8n jsonBody double-quoted JS-strings."""
    return text.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def nl_avoid_calques_block() -> str:
    if not _BLOCK_PATH.is_file():
        return ""
    return _BLOCK_PATH.read_text(encoding="utf-8").strip()


def nl_avoid_calques_block_js_literal() -> str:
    """Geëscaped voor embedding in n8n jsonBody double-quoted JS-strings."""
    block = nl_avoid_calques_block()
    if not block:
        return ""
    return _js_string_literal(block)


def hba_regelbreuk_definitie() -> str:
    """Zelfde definitie als de HBA-score (PHP): CE-03 zelfcontrole en CE-04 review."""
    if not _REGELBREUK_PATH.is_file():
        raise SystemExit(f"Missing {_REGELBREUK_PATH}")
    return _REGELBREUK_PATH.read_text(encoding="utf-8").strip()


def hba_regelbreuk_definitie_js_literal() -> str:
    return _js_string_literal(hba_regelbreuk_definitie())


def hba_regel_letterlijk_eis() -> str:
    if not _REGEL_LETTERLIJK_PATH.is_file():
        return ""
    return _REGEL_LETTERLIJK_PATH.read_text(encoding="utf-8").strip()


def hba_regel_letterlijk_eis_js_literal() -> str:
    return _js_string_literal(hba_regel_letterlijk_eis())


def hba_merkbewering_definitie() -> str:
    if not _MERKBEWERING_PATH.is_file():
        raise SystemExit(f"Missing {_MERKBEWERING_PATH}")
    return _MERKBEWERING_PATH.read_text(encoding="utf-8").strip()


def hba_merkbewering_definitie_js_literal() -> str:
    return _js_string_literal(hba_merkbewering_definitie())


def hba_flags_definitie() -> str:
    if not _FLAGS_PATH.is_file():
        return ""
    return _FLAGS_PATH.read_text(encoding="utf-8").strip()


def nl_avoid_calques_system_suffix() -> str:
    lit = nl_avoid_calques_block_js_literal()
    return f"\\n\\n{lit}" if lit else ""


def patch_json_body_system_prompt(body: str) -> str:
    """Voeg NL-calque-blok toe aan de eerste system: \"...\" in een n8n jsonBody."""
    if NL_CALQUES_MARKER in body:
        return body
    suffix = nl_avoid_calques_system_suffix()
    if not suffix:
        return body

    key = 'system: "'
    pos = body.find(key)
    if pos < 0:
        return body

    i = pos + len(key)
    while i < len(body):
        ch = body[i]
        if ch == "\\":
            i += 2
            continue
        if ch == '"':
            return body[:i] + suffix + body[i:]
        i += 1

    return body
