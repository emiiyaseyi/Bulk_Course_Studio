"""Build the two Moodle install ZIPs into dist/ (and copy them to the project root).

Each run first bumps $plugin->version in both version.php files, so Moodle
always sees the reinstalled ZIP as an upgrade. Use --no-bump to rebuild the
ZIPs without changing the version.

    python tools/build_zips.py
"""
import datetime
import os
import re
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PLUGINS = ['tool_bulkcoursestudio', 'mod_bulkcoursestudio']
VERSION_RE = re.compile(r'(\$plugin->version\s*=\s*)(\d{10})')


def bump(plugin):
    path = os.path.join(ROOT, plugin, 'version.php')
    with open(path, encoding='utf8', newline='') as handle:
        text = handle.read()
    match = VERSION_RE.search(text)
    old = int(match.group(2))
    today = int(datetime.date.today().strftime('%Y%m%d')) * 100
    new = max(old + 1, today)
    text = text[:match.start(2)] + str(new) + text[match.end(2):]
    with open(path, 'w', encoding='utf8', newline='') as handle:
        handle.write(text)
    return old, new


def current(plugin):
    with open(os.path.join(ROOT, plugin, 'version.php'), encoding='utf8') as handle:
        return int(VERSION_RE.search(handle.read()).group(2))


def build(plugin):
    os.makedirs(os.path.join(ROOT, 'dist'), exist_ok=True)
    out = os.path.join(ROOT, 'dist', plugin + '.zip')
    src = os.path.join(ROOT, plugin)
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as archive:
        for folder, _, files in os.walk(src):
            for name in files:
                full = os.path.join(folder, name)
                rel = os.path.relpath(full, src).replace(os.sep, '/')
                # Moodle expects the ZIP root folder to be the plugin name.
                archive.write(full, 'bulkcoursestudio/' + rel)
    # Also keep a copy next to the project folders, so the ZIPs you pick up are never stale.
    import shutil
    shutil.copyfile(out, os.path.join(ROOT, plugin + '.zip'))
    return out


if __name__ == '__main__':
    for plugin in PLUGINS:
        if '--no-bump' in sys.argv:
            print(plugin, 'version', current(plugin), '(not bumped)')
        else:
            old, new = bump(plugin)
            print(plugin, 'version', old, '->', new)
        print('  built', build(plugin))
