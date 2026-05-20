#!/usr/bin/env python3
"""Phase 5: Migrate remaining `Model::CONSTANT` references to `Enum::Case->value`.

Strategy:
- For each .php file under app/, database/, tests/, resources/views/, search for
  Model::CONST patterns. Replace with Enum::Case->value AND ensure the enum is
  imported (use App\\Enums\\<Domain>\\<Name>;) when not already present.
- Doesn't touch model files themselves (they own the constants).
"""
import re
import sys
from pathlib import Path

ROOT = Path('/home/schuppeliusd/workDiary')

# (Model, CONST) -> (EnumFqcn, Case, BackingType)
# BackingType decides whether to suffix with ->value (we always want ->value here
# since the original const was a scalar).
M = {}

def add_group(model, fqcn_short, items):
    """items: list of (CONST_NAME, CaseName)"""
    for const, case in items:
        M[(model, const)] = (fqcn_short, case)

add_group('Project', 'ProjectStatus', [
    ('STATUS_ACTIVE', 'Active'),
    ('STATUS_PAUSED', 'Paused'),
    ('STATUS_ARCHIVED', 'Archived'),
])
add_group('Tour', 'TourStatus', [
    ('STATUS_DRAFT', 'Draft'),
    ('STATUS_PLANNED', 'Planned'),
    ('STATUS_IN_PROGRESS', 'InProgress'),
    ('STATUS_COMPLETED', 'Completed'),
    ('STATUS_CANCELLED', 'Cancelled'),
])
add_group('Task', 'TaskStatus', [
    ('STATUS_OPEN', 'Open'),
    ('STATUS_IN_PROGRESS', 'InProgress'),
    ('STATUS_DONE', 'Done'),
])
add_group('Task', 'TaskPriority', [
    ('PRIORITY_LOW', 'Low'),
    ('PRIORITY_MEDIUM', 'Medium'),
    ('PRIORITY_HIGH', 'High'),
    ('PRIORITY_URGENT', 'Urgent'),
])
add_group('Vacation', 'VacationType', [
    ('TYPE_VACATION', 'Vacation'),
    ('TYPE_SICK', 'Sick'),
    ('TYPE_SPECIAL', 'Special'),
    ('TYPE_UNPAID', 'Unpaid'),
])
add_group('Vacation', 'VacationStatus', [
    ('STATUS_PENDING', 'Pending'),
    ('STATUS_APPROVED', 'Approved'),
    ('STATUS_REJECTED', 'Rejected'),
    ('STATUS_CANCELLED', 'Cancelled'),
])
add_group('Timesheet', 'TimesheetStatus', [
    ('STATUS_DRAFT', 'Draft'),
    ('STATUS_SUBMITTED', 'Submitted'),
    ('STATUS_SIGNED', 'Signed'),
    ('STATUS_LOCKED', 'Locked'),
])
add_group('Timesheet', 'TimesheetKind', [
    ('KIND_PROJECT', 'Project'),
    ('KIND_PERSONAL_DAY', 'PersonalDay'),
])
add_group('TimeEntry', 'TimeEntryKind', [
    ('KIND_WORK', 'Work'),
    ('KIND_TRAVEL', 'Travel'),
    ('KIND_STANDBY', 'Standby'),
])
add_group('TimeEntry', 'TimeEntryActivityType', [
    ('ACTIVITY_PROJECT', 'Project'),
    ('ACTIVITY_ADMIN', 'Admin'),
    ('ACTIVITY_TRAINING', 'Training'),
    ('ACTIVITY_MEETING', 'Meeting'),
    ('ACTIVITY_INTERNAL', 'Internal'),
    ('ACTIVITY_TRAVEL', 'Travel'),
    ('ACTIVITY_BREAK', 'Break_'),
    ('ACTIVITY_ABSENCE', 'Absence'),
    ('ACTIVITY_STANDBY', 'Standby'),
    ('ACTIVITY_OTHER', 'Other'),
])
add_group('Attendance', 'AttendanceStatus', [
    ('STATUS_OPEN', 'Open'),
    ('STATUS_CLOSED', 'Closed'),
    ('STATUS_AUTO_CLOSED', 'AutoClosed'),
    ('STATUS_ADJUSTED', 'Adjusted'),
    ('STATUS_CANCELLED', 'Cancelled'),
])
add_group('Attendance', 'AttendanceSource', [
    ('SOURCE_CLOCK', 'Clock'),
    ('SOURCE_MANUAL', 'Manual'),
    ('SOURCE_IMPORT', 'Import'),
    ('SOURCE_AUTO_CLOSE', 'AutoClose'),
])
add_group('ScheduledShift', 'ScheduledShiftStatus', [
    ('STATUS_DRAFT', 'Draft'),
    ('STATUS_PUBLISHED', 'Published'),
    ('STATUS_CONFIRMED', 'Confirmed'),
    ('STATUS_CANCELLED', 'Cancelled'),
])
add_group('DutyPlan', 'DutyPlanStatus', [
    ('STATUS_DRAFT', 'Draft'),
    ('STATUS_PUBLISHED', 'Published'),
])
add_group('DutyPlan', 'DutyPlanPeriodType', [
    ('PERIOD_DAILY', 'Daily'),
    ('PERIOD_WEEKLY', 'Weekly'),
    ('PERIOD_MONTHLY', 'Monthly'),
])
add_group('RecurrenceRule', 'RecurrenceFrequency', [
    ('FREQ_DAILY', 'Daily'),
    ('FREQ_WEEKLY', 'Weekly'),
    ('FREQ_MONTHLY', 'Monthly'),
    ('FREQ_YEARLY', 'Yearly'),
])
add_group('TravelLog', 'TravelLogVehicle', [
    ('VEHICLE_COMPANY', 'Company'),
    ('VEHICLE_PRIVATE', 'Private_'),
    ('VEHICLE_RENTAL', 'Rental'),
    ('VEHICLE_PUBLIC', 'PublicTransport'),
    ('VEHICLE_BICYCLE', 'Bicycle'),
    ('VEHICLE_FOOT', 'Foot'),
    ('VEHICLE_OTHER', 'Other'),
])
add_group('Vehicle', 'VehicleType', [
    ('TYPE_CAR', 'Car'),
    ('TYPE_VAN', 'Van'),
    ('TYPE_TRUCK', 'Truck'),
    ('TYPE_BICYCLE', 'Bicycle'),
    ('TYPE_OTHER', 'Other'),
])
add_group('Vehicle', 'VehiclePropulsion', [
    ('PROPULSION_DIESEL', 'Diesel'),
    ('PROPULSION_PETROL', 'Petrol'),
    ('PROPULSION_GAS', 'Gas'),
    ('PROPULSION_HYBRID', 'Hybrid'),
    ('PROPULSION_ELECTRIC', 'Electric'),
    ('PROPULSION_MUSCLE', 'Muscle'),
    ('PROPULSION_OTHER', 'Other'),
])
add_group('Vehicle', 'VehicleOwnership', [
    ('OWNERSHIP_OWNED', 'Owned'),
    ('OWNERSHIP_LEASED', 'Leased'),
    ('OWNERSHIP_RENTAL', 'Rental'),
])
add_group('SickLeave', 'SickLeaveKind', [
    ('KIND_INITIAL', 'Initial'),
    ('KIND_FOLLOW_UP', 'FollowUp'),
])
add_group('ActivityCategory', 'ActivityCategoryType', [
    ('TYPE_PROJECT', 'Project'),  # may not exist; safe if absent
    ('TYPE_ADMIN', 'Admin'),
    ('TYPE_TRAINING', 'Training'),
    ('TYPE_MEETING', 'Meeting'),
    ('TYPE_INTERNAL', 'Internal'),
    ('TYPE_TRAVEL', 'Travel'),
    ('TYPE_BREAK', 'Break_'),
    ('TYPE_ABSENCE', 'Absence'),
    ('TYPE_STANDBY', 'Standby'),
    ('TYPE_OTHER', 'Other'),
])

ENUM_FQCN = {
    'ProjectStatus': 'App\\Enums\\Project\\ProjectStatus',
    'TourStatus': 'App\\Enums\\Tour\\TourStatus',
    'TaskStatus': 'App\\Enums\\Task\\TaskStatus',
    'TaskPriority': 'App\\Enums\\Task\\TaskPriority',
    'VacationType': 'App\\Enums\\Vacation\\VacationType',
    'VacationStatus': 'App\\Enums\\Vacation\\VacationStatus',
    'TimesheetStatus': 'App\\Enums\\Timesheet\\TimesheetStatus',
    'TimesheetKind': 'App\\Enums\\Timesheet\\TimesheetKind',
    'TimeEntryKind': 'App\\Enums\\TimeEntry\\TimeEntryKind',
    'TimeEntryActivityType': 'App\\Enums\\TimeEntry\\TimeEntryActivityType',
    'AttendanceStatus': 'App\\Enums\\Attendance\\AttendanceStatus',
    'AttendanceSource': 'App\\Enums\\Attendance\\AttendanceSource',
    'ScheduledShiftStatus': 'App\\Enums\\Shift\\ScheduledShiftStatus',
    'DutyPlanStatus': 'App\\Enums\\Shift\\DutyPlanStatus',
    'DutyPlanPeriodType': 'App\\Enums\\Shift\\DutyPlanPeriodType',
    'RecurrenceFrequency': 'App\\Enums\\Recurrence\\RecurrenceFrequency',
    'TravelLogVehicle': 'App\\Enums\\Travel\\TravelLogVehicle',
    'VehicleType': 'App\\Enums\\Vehicle\\VehicleType',
    'VehiclePropulsion': 'App\\Enums\\Vehicle\\VehiclePropulsion',
    'VehicleOwnership': 'App\\Enums\\Vehicle\\VehicleOwnership',
    'SickLeaveKind': 'App\\Enums\\Sickness\\SickLeaveKind',
    'ActivityCategoryType': 'App\\Enums\\Activity\\ActivityCategoryType',
}

# Build per-model regex
PER_MODEL = {}
for (model, const), (enum, case) in M.items():
    PER_MODEL.setdefault(model, []).append((const, enum, case))


def process_file(path: Path) -> bool:
    text = path.read_text()
    original = text
    used_enums = set()

    for model, mappings in PER_MODEL.items():
        # Replace each Model::CONST → Enum::Case->value
        for const, enum, case in mappings:
            pat = re.compile(r'\b' + re.escape(model) + r'::' + re.escape(const) + r'\b')
            new_text, n = pat.subn(f'{enum}::{case}->value', text)
            if n > 0:
                text = new_text
                used_enums.add(enum)

    if not used_enums:
        return False

    # Add missing imports
    lines = text.split('\n')
    # Find namespace + last existing 'use ' at top
    use_indices = [i for i, l in enumerate(lines) if re.match(r'^use\s+\S+;', l)]
    insert_after = use_indices[-1] if use_indices else None
    if insert_after is None:
        # Find 'namespace' line, insert blank+use after
        for i, l in enumerate(lines):
            if l.startswith('namespace '):
                insert_after = i + 1  # blank line after namespace; insert after that
                break
    if insert_after is None:
        # Probably blade; place at top after <?php
        for i, l in enumerate(lines):
            if l.strip().startswith('<?php'):
                insert_after = i
                break

    new_imports = []
    existing = set()
    for i in use_indices:
        m = re.match(r'^use\s+(\S+);', lines[i])
        if m:
            existing.add(m.group(1).replace('\\\\', '\\'))
    for enum in used_enums:
        fqcn = ENUM_FQCN[enum]
        if fqcn not in existing:
            new_imports.append(f'use {fqcn};')

    if new_imports:
        lines[insert_after+1:insert_after+1] = new_imports

    new_text = '\n'.join(lines)
    if new_text != original:
        path.write_text(new_text)
        return True
    return False


def main():
    targets = []
    for sub in ['app', 'database', 'tests']:
        targets += list((ROOT / sub).rglob('*.php'))
    targets += list((ROOT / 'resources/views').rglob('*.blade.php'))

    # Skip Model files themselves (they OWN the constants)
    skip_paths = {
        ROOT / 'app/Models' / f'{m}.php' for m in PER_MODEL.keys()
    }

    changed = []
    for p in targets:
        if p in skip_paths:
            continue
        try:
            if process_file(p):
                changed.append(str(p.relative_to(ROOT)))
        except Exception as e:
            print(f'FAIL {p}: {e}', file=sys.stderr)

    print(f'Changed {len(changed)} files:')
    for c in changed:
        print(f'  {c}')

if __name__ == '__main__':
    main()
