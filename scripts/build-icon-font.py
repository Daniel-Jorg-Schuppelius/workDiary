#!/usr/bin/env python3
#
# Created on   : Tue Sep 29 2026
# Author       : Daniel Jörg Schuppelius
# Author Uri   : https://schuppelius.org
# Filename     : build-icon-font.py
# License      : AGPL-3.0-or-later
# License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
#
"""Erzeugt resources/fonts/material-symbols-outlined.woff2 aus dem npm-Paket.

Die Achsen, die die App nie verändert, werden festgesetzt (GRAD 0, opsz 24),
wght auf den genutzten Bereich 400–700 begrenzt; FILL und alle Glyphen
bleiben. Aus 3,8 MB werden so ~0,8 MB. Kein Subset: Icon-Namen sind in
Formularen frei eingebbar.

Nach jedem Update von `material-symbols` neu ausführen, sonst schlägt
tests/frontend/icon-font.test.mjs an:

    pip install fonttools brotli
    python3 scripts/build-icon-font.py
"""
import hashlib
import json
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

ROOT = Path(__file__).resolve().parent.parent
PACKAGE = ROOT / 'node_modules' / 'material-symbols'
SOURCE = PACKAGE / 'material-symbols-outlined.woff2'
TARGET = ROOT / 'resources' / 'fonts' / 'material-symbols-outlined.woff2'
MANIFEST = TARGET.with_suffix('.json')

# Muss zum @font-face in resources/css/app.css passen (font-weight 400 700).
LIMITS = {'GRAD': 0, 'opsz': 24, 'wght': (400, 700)}


def main() -> None:
    # Zeitstempel der Quelle behalten: gleiche Eingabe, gleiche Datei, kein Git-Diff.
    font = instancer.instantiateVariableFont(TTFont(SOURCE, recalcTimestamp=False), LIMITS)
    font.flavor = 'woff2'
    TARGET.parent.mkdir(parents=True, exist_ok=True)
    font.save(TARGET)

    package = json.loads((PACKAGE / 'package.json').read_text(encoding='utf-8'))
    MANIFEST.write_text(json.dumps({
        'package': 'material-symbols',
        'version': package['version'],
        'license': package['license'],
        # Apache-2.0 §4(b): Hinweis auf die Änderung an der abgeleiteten Datei.
        'notice': 'Abgeleitet von material-symbols-outlined.woff2 (Google Material Symbols): '
                  'Achsen GRAD/opsz festgesetzt, wght auf 400–700 begrenzt (scripts/build-icon-font.py).',
        'source_sha256': hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
        'limits': {axis: list(value) if isinstance(value, tuple) else value for axis, value in LIMITS.items()},
    }, indent=4, ensure_ascii=False) + '\n', encoding='utf-8')

    print(f'{TARGET.relative_to(ROOT)}: {SOURCE.stat().st_size:,} → {TARGET.stat().st_size:,} Bytes')


if __name__ == '__main__':
    main()
