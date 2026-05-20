#!/usr/bin/env python3
"""Phase 5 step 2: Remove @deprecated public const/static blocks from model files.

Approach:
- For each model file in TARGETS, scan top-level lines
- Identify @deprecated docblocks (single-line /** ... */ OR multi-line) and
  the immediately following `public const X = ...` (single-line or multi-line array)
  or `public static $X = ...` block. Remove both.
- Skip if the const block is NOT preceded by a @deprecated marker.
"""
import re
import sys
from pathlib import Path

ROOT = Path('/home/schuppeliusd/workDiary')

TARGETS = [
    'app/Models/ActivityCategory.php',
    'app/Models/Attendance.php',
    'app/Models/DiaryEntry.php',
    'app/Models/DutyPlan.php',
    'app/Models/Project.php',
    'app/Models/RecurrenceRule.php',
    'app/Models/ScheduledShift.php',
    'app/Models/SickLeave.php',
    'app/Models/Task.php',
    'app/Models/TimeEntry.php',
    'app/Models/Timesheet.php',
    'app/Models/Tour.php',
    'app/Models/TravelLog.php',
    'app/Models/User.php',
    'app/Models/Vacation.php',
    'app/Models/Vehicle.php',
]

CONST_NAME_RE = re.compile(
    r'^\s*public\s+(?:const\s+[A-Z_][A-Za-z0-9_]*|static\s+(?:array\s+|string\s+|int\s+|\?array\s+|\?string\s+|\?int\s+)?\$[A-Za-z_][A-Za-z0-9_]*)'
)


def find_deprecated_block_end(lines, start):
    """lines[start] is '/**' opening. Return end index (line containing '*/') and whether
    the doc has @deprecated.
    """
    has_deprecated = False
    i = start
    n = len(lines)
    # Single-line: "/** @deprecated ... */"
    if '*/' in lines[i] and lines[i].strip().startswith('/**'):
        has_deprecated = '@deprecated' in lines[i]
        return i, has_deprecated
    while i < n:
        if '@deprecated' in lines[i]:
            has_deprecated = True
        if '*/' in lines[i]:
            return i, has_deprecated
        i += 1
    return n - 1, has_deprecated


def find_statement_end(lines, start):
    """Find the line where the statement ending in ';' completes.
    Handles multi-line array literals.
    """
    i = start
    n = len(lines)
    depth = 0
    while i < n:
        depth += lines[i].count('[') + lines[i].count('(')
        depth -= lines[i].count(']') + lines[i].count(')')
        if depth == 0 and lines[i].rstrip().endswith(';'):
            return i
        i += 1
    return n - 1


def process_file(path: Path) -> int:
    text = path.read_text()
    lines = text.split('\n')
    keep = [True] * len(lines)
    n = len(lines)
    i = 0
    while i < n:
        line = lines[i]
        stripped = line.strip()
        if stripped.startswith('/**'):
            doc_end, has_dep = find_deprecated_block_end(lines, i)
            if has_dep:
                # Look ahead: skip blank lines, find next public const/static
                j = doc_end + 1
                while j < n and lines[j].strip() == '':
                    j += 1
                if j < n and CONST_NAME_RE.match(lines[j]):
                    stmt_end = find_statement_end(lines, j)
                    # Mark doc + (any blanks) + statement for removal
                    for k in range(i, stmt_end + 1):
                        keep[k] = False
                    # Also collapse one trailing blank line
                    if stmt_end + 1 < n and lines[stmt_end + 1].strip() == '':
                        keep[stmt_end + 1] = False
                    i = stmt_end + 1
                    continue
            i = doc_end + 1
            continue
        i += 1

    new_lines = [l for l, k in zip(lines, keep) if k]
    new_text = '\n'.join(new_lines)
    if new_text != text:
        path.write_text(new_text)
        return text.count('\n') - new_text.count('\n')
    return 0


def main():
    total = 0
    for rel in TARGETS:
        p = ROOT / rel
        n = process_file(p)
        total += n
        print(f'{rel}: removed {n} lines')
    print(f'TOTAL: {total} lines removed')


if __name__ == '__main__':
    main()
