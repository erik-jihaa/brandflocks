"""Shared fixes for Content Engine n8n workflow JSON (MySQL placeholders, schema nodes)."""
from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any


def question_marks_to_n8n_placeholders(query: str) -> str:
    """Convert MySQL-native ? to n8n MySQL node v2 $1, $2, … notation."""
    counter = 0

    def repl(_: re.Match[str]) -> str:
        nonlocal counter
        counter += 1
        return f"${counter}"

    return re.sub(r"\?", repl, query or "")


def ensure_n8n_mysql_placeholders(query: str) -> str:
    """Keep $N placeholders; convert legacy ? in query text to $N."""
    if not query or re.search(r"\$\d+", query):
        return query
    return question_marks_to_n8n_placeholders(query)


def _node_index(workflow: dict[str, Any]) -> dict[str, dict[str, Any]]:
    return {node["name"]: node for node in workflow.get("nodes", [])}


def remove_node_and_rewire(workflow: dict[str, Any], node_name: str) -> None:
    connections: dict[str, Any] = workflow.setdefault("connections", {})
    next_nodes = connections.get(node_name, {}).get("main", [[]])
    next_target = next_nodes[0][0]["node"] if next_nodes and next_nodes[0] else None

    for branches in connections.values():
        for branch in branches.get("main", []):
            for link in branch:
                if link.get("node") == node_name and next_target:
                    link["node"] = next_target

    connections.pop(node_name, None)
    workflow["nodes"] = [node for node in workflow["nodes"] if node.get("name") != node_name]


def fix_mysql_nodes(workflow: dict[str, Any]) -> None:
    for node in workflow.get("nodes", []):
        if node.get("type") != "n8n-nodes-base.mySql":
            continue
        params = node.setdefault("parameters", {})
        if "query" in params:
            params["query"] = ensure_n8n_mysql_placeholders(params["query"])


def remove_ensure_table_nodes(workflow: dict[str, Any]) -> None:
    for name in list(_node_index(workflow)):
        if name.startswith("Ensure "):
            remove_node_and_rewire(workflow, name)


def replace_ce04_status_http_with_mysql(workflow: dict[str, Any]) -> None:
    if not workflow.get("name", "").startswith("CE-04"):
        return

    by_name = _node_index(workflow)
    node = by_name.get("Set Status Feedback")
    if not node or node.get("type") == "n8n-nodes-base.mySql":
        return

    node["type"] = "n8n-nodes-base.mySql"
    node["typeVersion"] = 2.1
    node["credentials"] = {
        "mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}
    }
    node["parameters"] = {
        "operation": "executeQuery",
        "query": "UPDATE articles SET status = 'feedback' WHERE slot_id = $1",
        "options": {
            "queryReplacement": "={{ $('Validate Article').item.json.slot_id }}",
        },
    }


def finalize_workflow(workflow: dict[str, Any]) -> dict[str, Any]:
    remove_ensure_table_nodes(workflow)
    fix_mysql_nodes(workflow)
    replace_ce04_status_http_with_mysql(workflow)
    return workflow


def apply_env_patch(workflow: dict[str, Any]) -> dict[str, Any]:
    from n8n_env import patch_workflow_for_env, target_env

    if target_env() != "production":
        patch_workflow_for_env(workflow)
    return workflow


def write_workflow(workflow: dict[str, Any], path: Path) -> None:
    finalize_workflow(workflow)
    apply_env_patch(workflow)
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(workflow, indent=2) + "\n", encoding="utf-8")


def patch_workflow_file(path: Path) -> None:
    workflow = json.loads(path.read_text(encoding="utf-8"))
    finalize_workflow(workflow)
    apply_env_patch(workflow)
    path.write_text(json.dumps(workflow, indent=2) + "\n", encoding="utf-8")
    print(f"Patched {path.name}")
