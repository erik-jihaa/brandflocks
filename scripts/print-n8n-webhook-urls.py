#!/usr/bin/env python3
"""Print N8N_*_WEBHOOK_URL lines for the current CE_N8N_TARGET."""
from n8n_env import load_env, print_webhook_env_lines, target_env

env = target_env()
load_env(env if env != "production" else None, override=True)
print_webhook_env_lines()
