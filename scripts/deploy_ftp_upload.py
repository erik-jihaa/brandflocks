"""Deploy upload naar ce.igisnext.com — lftp (SFTP/FTPS/FTP)."""
from __future__ import annotations

import argparse
import re
import shutil
import subprocess
import sys
from pathlib import Path


def parse_env_value(raw: str) -> str:
    raw = raw.strip()
    if not raw:
        return ''
    if raw[0] in '"\'':
        q = raw[0]
        i = 1
        out = []
        while i < len(raw):
            if raw[i] == '\\' and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == q:
                break
            out.append(raw[i])
            i += 1
        return ''.join(out)
    if ' #' in raw:
        raw = raw.split(' #', 1)[0]
    return raw.strip()


def load_ftp_config(env_path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for line in env_path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        key, val = line.split('=', 1)
        values[key.strip()] = parse_env_value(val)

    protocol = (values.get('FTP_PROTOCOL') or 'ftps').lower()
    port = values.get('FTP_PORT', '').strip()
    if not port:
        port = '22' if protocol == 'sftp' else '21'

    host = values.get('FTP_HOST', '')
    user = values.get('FTP_USER', '')
    password = values.get('FTP_PASSWORD') or values.get('FTP_PASS') or ''
    remote_root = (values.get('FTP_REMOTE_PATH') or '/').strip('/') or '.'

    if not host or not user or not password:
        raise SystemExit('FTP_HOST, FTP_USER en FTP_PASSWORD (of FTP_PASS) vereist in .env')

    return {
        'host': host,
        'port': port,
        'user': user,
        'password': password,
        'protocol': protocol,
        'remote_root': remote_root,
    }


def connection_profiles(cfg: dict[str, str]) -> list[dict[str, str]]:
    """Probeervolgorde: expliciete config, daarna gangbare fallbacks."""
    primary = dict(cfg)
    seen: set[tuple[str, str, str]] = set()
    profiles: list[dict[str, str]] = []

    def add(profile: dict[str, str]) -> None:
        key = (profile['protocol'], profile['port'], profile['user'])
        if key in seen:
            return
        seen.add(key)
        profiles.append(profile)

    add(primary)

    user = cfg['user']
    is_ce_sub = 'erik-ce@' in user or user.endswith('@ce.igisnext.com')

    if is_ce_sub or '@staging.igisnext.com' in user:
        # Sub-FTP-account (CE of staging-subdomein): meestal geen SFTP op 22.
        # Staging-accounts op cPanel gebruiken vaak plain FTP op 21, geen FTPS.
        for protocol, port in (('ftp', '21'), ('ftps', '21')):
            fallback = dict(cfg)
            fallback['protocol'] = protocol
            fallback['port'] = port
            add(fallback)
    elif user.endswith('@igisnext.com'):
        # Hoofdaccount: FileZilla gebruikt SFTP 22.
        for protocol, port in (('sftp', '22'), ('ftps', '21'), ('ftp', '21')):
            fallback = dict(cfg)
            fallback['protocol'] = protocol
            fallback['port'] = port
            add(fallback)

    return profiles


def require_lftp() -> str:
    path = shutil.which('lftp')
    if not path:
        raise SystemExit(
            'Deploy vereist lftp.\n'
            'Installeer: brew install lftp'
        )
    return path


def lftp_settings(protocol: str) -> str:
    lines = [
        'set net:timeout 30',
        'set net:max-retries 2',
        'set cmd:fail-exit true',
    ]
    if protocol == 'sftp':
        lines.append('set sftp:connect-program "ssh -a -x -oStrictHostKeyChecking=accept-new"')
    elif protocol == 'ftps':
        lines.extend([
            'set ftp:passive-mode true',
            'set ftp:ssl-force true',
            'set ssl:verify-certificate no',
        ])
    else:
        lines.extend([
            'set ftp:passive-mode true',
            'set ftp:ssl-allow no',
        ])
    return '\n'.join(lines) + '\n'


def lftp_open(cfg: dict[str, str]) -> str:
    user = cfg['user']
    password = cfg['password'].replace('"', '\\"')
    host = cfg['host']
    port = cfg['port']
    protocol = cfg['protocol']
    scheme = {'sftp': 'sftp', 'ftps': 'ftps'}.get(protocol, 'ftp')
    return f'open -u "{user}","{password}" {scheme}://{host}:{port}; '


def lftp_run(cfg: dict[str, str], body: str, timeout: int = 600) -> None:
    require_lftp()
    script = lftp_settings(cfg['protocol']) + lftp_open(cfg) + body
    proc = subprocess.run(
        ['lftp', '-c', script],
        capture_output=True,
        text=True,
        timeout=timeout,
    )
    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout or 'onbekend').strip()
        raise RuntimeError(err)


def lftp_probe(cfg: dict[str, str]) -> tuple[bool, str]:
    try:
        lftp_run(cfg, 'pwd; ls; quit\n', timeout=45)
        return True, ''
    except subprocess.TimeoutExpired:
        return False, 'timeout'
    except RuntimeError as exc:
        return False, str(exc)


def pick_working_profile(cfg: dict[str, str]) -> dict[str, str]:
    errors: list[str] = []
    for profile in connection_profiles(cfg):
        label = f"{profile['protocol']}://{profile['host']}:{profile['port']} user={profile['user']}"
        print(f'Probe {label}...')
        ok, err = lftp_probe(profile)
        if ok:
            print(f'OK — {label}')
            return profile
        short = err.split('\n')[0][:120]
        print(f'  mislukt: {short}')
        errors.append(f'{label}: {short}')

    raise SystemExit(
        'Geen werkende FTP-verbinding.\n'
        + '\n'.join(f'  • {line}' for line in errors)
        + '\n\nTips:\n'
        '  • CE-subaccount erik-ce@… heeft vaak géén SFTP (poort 22) — gebruik FTPS poort 21.\n'
        '  • Studio/FileZilla: test erik@igisnext.com SFTP poort 22 → map naar CE in cPanel.\n'
        '  • Zet dan in .env: FTP_USER=erik@igisnext.com FTP_PROTOCOL=sftp FTP_PORT=22\n'
        '    FTP_REMOTE_PATH=../ce.igisnext.com  (pad uit cPanel → Subdomains)\n'
        '  • Of: ./scripts/deploy-ftp-probe.sh om mappen te vinden.'
    )


def remote_rel(cfg: dict[str, str], rel: str) -> str:
    root = cfg['remote_root']
    if root in ('', '.', '/'):
        return rel
    return f'{root}/{rel}'.replace('//', '/')


def upload_via_lftp(cfg: dict[str, str], targets: list[tuple[Path, str]]) -> int:
    lines = [f"cd {cfg['remote_root']};"]
    for local, rel in targets:
        remote = remote_rel(cfg, rel)
        remote_dir = '/'.join(remote.split('/')[:-1])
        if remote_dir:
            lines.append(f'mkdir -f {remote_dir};')
        lines.append(f'put "{local}" -o "{remote}";')
        print('UP', rel)
    lines.append('quit')
    lftp_run(cfg, '\n'.join(lines), timeout=max(600, len(targets) * 10))
    return len(targets)


def path_matches_exclude(rel: str, exclude_names: list[str], exclude_globs: list[str]) -> bool:
    """Return True if rel should be skipped (same semantics as lftp --exclude / --exclude-glob)."""
    from fnmatch import fnmatch

    name = Path(rel).name
    for pattern in exclude_names:
        if name == pattern or rel == pattern:
            return True
    for pattern in exclude_globs:
        if fnmatch(rel, pattern) or fnmatch(name, pattern):
            return True
        # Mirror-style: 'output/*' also skips everything under output/
        if pattern.endswith('/*'):
            prefix = pattern[:-1]  # 'output/'
            if rel == prefix.rstrip('/') or rel.startswith(prefix):
                return True
    return False


def upload_via_lftp_mirror(
    cfg: dict[str, str],
    web: Path,
    exclude_names: list[str] | None = None,
    exclude_globs: list[str] | None = None,
) -> int:
    # Default CE exclusions — always on; extra lists only from CLI (LinkedIn deploy).
    names = ['.DS_Store', *(exclude_names or [])]
    globs = ['blog/*', *(exclude_globs or [])]

    count = 0
    for file in web.rglob('*'):
        if not file.is_file():
            continue
        rel = file.relative_to(web).as_posix()
        if path_matches_exclude(rel, names, globs):
            continue
        count += 1

    exclude_flags = ' '.join(
        [f"--exclude-glob '{g}'" for g in globs] + [f"--exclude {n}" for n in names]
    )
    index_put = ''
    index_html = web / 'index.html'
    if index_html.is_file() and not path_matches_exclude('index.html', names, globs):
        # Script begint met `cd remote_root` — bestemming is relatief aan die map.
        index_put = f'put "{index_html}" -o "index.html";\n'
    body = f"""cd {cfg['remote_root']};
lcd "{web}";
mirror --reverse --verbose --parallel=4 --no-perms --overwrite \\
  {exclude_flags} . .;
{index_put}quit
"""
    print(f'mirror {web} → {cfg["remote_root"]} ({count} files)')
    if exclude_names or exclude_globs:
        print('  extra excludes:', ', '.join([*(exclude_globs or []), *(exclude_names or [])]))
    lftp_run(cfg, body, timeout=max(900, count * 5))
    return count


def collect_targets(web: Path, mode: str) -> list[tuple[Path, str]]:
    if mode == 'quick':
        files = [
            'api/theme-repository.php',
            'api/home-repository.php',
            'api/publish-repository.php',
            'api/article-repository.php',
            'api/test-image.php',
            'api/articles-submit.php',
            'api/review-edits-repository.php',
            'api/fastlane-repository.php',
            'api/slot-repository.php',
            'api/articles.php',
            'api/articles-rewrite.php',
            'api/articles-reedit.php',
            'api/articles-soften-claim.php',
            'api/articles-claim-evidence.php',
            'api/research-quality.php',
            'api/admin-debug-research.php',
            'api/theme-repository.php',
            'api/hba-v2-repository.php',
            'api/hba-v2.php',
            'api/hba-v2-prompts.php',
            'api/uuid-helper.php',
            'api/review-repository.php',
            'api/account-repository.php',
            'api/session-repository.php',
            'api/platform-repository.php',
            'api/platform-actions-repository.php',
            'api/integrations-status-repository.php',
            'api/sources-sync.php',
            'api/sources-sync-repository.php',
            'api/sources-repository.php',
            'api/mailbox-helper.php',
            'api/joomla-publish-repository.php',
            'api/scrape-repository.php',
            'api/sources-inspiration-repository.php',
            'api/sources-inspiration.php',
            'api/index.php',
            'migrations/add_hba_research_verdicts.sql',
            'index.html',
            'images/test-image-square.png',
            'images/test-image-landscape.png',
        ]
        assets = sorted((web / 'assets').glob('index-*.js')) + sorted((web / 'assets').glob('index-*.css'))
        asset_names: list[str] = []
        index_html = web / 'index.html'
        if index_html.is_file():
            match = re.search(r'/assets/(index-[^."\\]+\.js)', index_html.read_text(encoding='utf-8'))
            if match:
                asset_names.append(f'assets/{match.group(1)}')
        for asset in assets:
            rel = f'assets/{asset.name}'
            if rel not in asset_names:
                asset_names.append(rel)
        for rel in asset_names[-3:]:
            files.append(rel)
        return [(web / rel, rel) for rel in files if (web / rel).is_file()]

    targets: list[tuple[Path, str]] = []
    for file in sorted(web.rglob('*')):
        if not file.is_file():
            continue
        rel = file.relative_to(web).as_posix()
        if rel.startswith('blog/'):
            continue
        targets.append((file, rel))
    return targets


def main() -> None:
    parser = argparse.ArgumentParser(description='FTP/SFTP upload via lftp')
    parser.add_argument('env_path', type=Path)
    parser.add_argument('web', type=Path)
    parser.add_argument('mode', nargs='?', default='full', choices=['full', 'quick'])
    parser.add_argument('--probe-only', action='store_true')
    parser.add_argument(
        '--exclude',
        action='append',
        default=[],
        help='Extra bestandsnaam/pad uitsluiten (alleen LinkedIn; CE-deploy geeft dit niet mee)',
    )
    parser.add_argument(
        '--exclude-glob',
        action='append',
        default=[],
        help='Extra glob uitsluiten, bv. output/* (alleen LinkedIn; CE-deploy geeft dit niet mee)',
    )
    args = parser.parse_args()

    base_cfg = load_ftp_config(args.env_path)
    cfg = pick_working_profile(base_cfg)

    if args.probe_only:
        print(f"remote_root={cfg['remote_root']}")
        return

    print(
        f"Upload via {cfg['protocol']}://{cfg['host']}:{cfg['port']} "
        f"user={cfg['user']} remote={cfg['remote_root']}"
    )

    if args.mode == 'quick':
        targets = collect_targets(args.web, 'quick')
        uploaded = upload_via_lftp(cfg, targets)
    else:
        uploaded = upload_via_lftp_mirror(
            cfg,
            args.web,
            exclude_names=args.exclude,
            exclude_globs=args.exclude_glob,
        )

    label = 'quick' if args.mode == 'quick' else 'ce.igisnext.com'
    print(f'Done — {uploaded} files uploaded ({label})')


if __name__ == '__main__':
    main()
