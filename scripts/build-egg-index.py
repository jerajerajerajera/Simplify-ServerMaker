#!/usr/bin/env python3
"""Build resources/catalog/game-index.json from local clones of the official Pelican egg repositories.

Usage: python3 scripts/build-egg-index.py /path/to/games-steamcmd /path/to/games-standalone
Download URLs are pinned to each repository's current commit, so the recorded sha256 stays valid.
"""
import hashlib, json, os, re, subprocess, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'resources', 'catalog', 'game-index.json')
CATEGORIES = {'games-steamcmd': 'Steam', 'games-standalone': 'Standalone'}


def git(repo, *args):
    return subprocess.check_output(['git', '-C', repo, *args], text=True).strip()


def slug(text):
    return re.sub(r'[^a-z0-9]+', '-', text.lower()).strip('-')


def main(repos):
    games = []
    for repo in repos:
        name = os.path.basename(os.path.normpath(repo))
        commit = git(repo, 'rev-parse', 'HEAD')
        for folder, _, files in os.walk(repo):
            if '/.git' in folder:
                continue
            for file in sorted(files):
                # Pelican format only; the repos also carry Pterodactyl copies of each egg.
                if not (file.startswith('egg-') and file.endswith('.json')) or file.startswith('egg-pterodactyl-'):
                    continue
                path = os.path.join(folder, file)
                raw = open(path, 'rb').read()
                try:
                    egg = json.loads(raw)
                except ValueError:
                    continue
                if not egg.get('name') or not egg.get('docker_images'):
                    continue
                relative = os.path.relpath(path, repo).replace(os.sep, '/')
                variables = {v.get('env_variable'): v.get('default_value') for v in egg.get('variables', [])}
                description = re.sub(r'\s+', ' ', str(egg.get('description') or '')).strip()
                games.append({
                    'id': slug(name + '-' + relative.rsplit('.', 1)[0]),
                    'name': egg['name'].strip(),
                    'category': CATEGORIES.get(name, name),
                    'folder': relative.split('/')[0].replace('_', ' '),
                    'description': description[:220] + ('…' if len(description) > 220 else ''),
                    'uuid': egg.get('uuid'),
                    'steam_app': str(variables.get('SRCDS_APPID') or '') or None,
                    'url': f'https://raw.githubusercontent.com/pelican-eggs/{name}/{commit}/{relative}',
                    'sha256': hashlib.sha256(raw).hexdigest(),
                })
    games.sort(key=lambda g: g['name'].lower())
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, 'w') as handle:
        json.dump({'source': 'https://github.com/pelican-eggs', 'games': games}, handle, separators=(',', ':'), ensure_ascii=False)
        handle.write('\n')
    print(f'{len(games)} games -> {os.path.relpath(OUT, ROOT)}')


if __name__ == '__main__':
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    main(sys.argv[1:])
