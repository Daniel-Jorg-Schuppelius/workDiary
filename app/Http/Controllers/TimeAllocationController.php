<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAllocationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{TimeAllocation, TimeEntry};
use App\Services\Timekeeping\TimeAllocationService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Zeitaufteilung (Feature 103, MVP-514): Dialog + Speichern der Anteile
 * eines Zeiteintrags. Autorisierung über die TimeEntry-Policy (`update`);
 * harte Sperren prüft der {@see TimeAllocationService}.
 */
class TimeAllocationController extends Controller {
    /** Dialog-Fragment (data-entry-modal-trigger lädt es per Fetch). */
    public function edit(TimeEntry $timeEntry): View {
        Gate::authorize('update', $timeEntry);

        return view('time-entries._allocations_dialog', [
            'entry' => $timeEntry->load('allocations'),
            'targetGroups' => $this->targetGroups(),
        ]);
    }

    public function update(Request $request, TimeEntry $timeEntry, TimeAllocationService $service): RedirectResponse {
        Gate::authorize('update', $timeEntry);

        $request->validate([
            'allocations' => ['nullable', 'array'],
            'allocations.*.target' => ['nullable', 'string'],
            'allocations.*.minutes' => ['nullable', 'integer', 'min:0'],
            'allocations.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.comment' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = [];
        foreach ((array) $request->input('allocations', []) as $raw) {
            $target = trim((string) ($raw['target'] ?? ''));
            $minutes = (int) ($raw['minutes'] ?? 0);
            // Leere Zeilen (kein Ziel oder keine Minuten) werden ignoriert.
            if ($target === '' || $minutes === 0) {
                continue;
            }

            [$alias, $sqid] = array_pad(explode(':', $target, 2), 2, '');
            $modelClass = TimeAllocation::TYPES[$alias] ?? null;
            $id = $modelClass !== null ? Sqid::decodeOrNumeric($modelClass, $sqid) : null;

            $rows[] = [
                'type' => $alias,
                'id' => (int) ($id ?? 0),
                'minutes' => $minutes,
                'quantity' => isset($raw['quantity']) && $raw['quantity'] !== '' ? (float) $raw['quantity'] : null,
                'comment' => isset($raw['comment']) && trim((string) $raw['comment']) !== '' ? trim((string) $raw['comment']) : null,
            ];
        }

        $service->replaceForEntry($timeEntry, $rows);

        return back()->with('success', __('allocation.flash.saved'));
    }

    /**
     * Auswahllisten je Dimension: Gruppen mit Label + Optionen
     * (value "alias:sqid"). Aufgaben/Assets bewusst nicht im Dialog v1
     * (unbegrenzt große Listen); freie Mandanten-Dimensionen (P2) als
     * eigene Gruppe je aktiviertem Typ, nur am heutigen Tag gültige Werte.
     *
     * @return list<array{label: string, options: list<array{value: string, name: string}>}>
     */
    private function targetGroups(): array {
        $sources = [
            'project' => \App\Models\Project::query()->orderBy('name')->pluck('name', 'id'),
            'cost_center' => \App\Models\CostCenter::query()->where('active', true)->orderBy('code')
                ->get(['id', 'code', 'label'])
                ->mapWithKeys(fn (\App\Models\CostCenter $c): array => [(int) $c->id => trim($c->code . ' — ' . $c->label)]),
            'site' => \App\Models\Site::query()->orderBy('name')->pluck('name', 'id'),
            // Fahrzeuge haben kein name-Feld: Label + Kennzeichen.
            'vehicle' => \App\Models\Vehicle::query()->orderBy('label')->get(['id', 'label', 'license_plate'])
                ->mapWithKeys(fn (\App\Models\Vehicle $v): array => [(int) $v->id => trim(($v->label ?? '') . ' ' . ($v->license_plate ?? '')) ?: '#' . $v->id]),
            // Tätigkeiten haben label statt name.
            'activity_category' => \App\Models\ActivityCategory::query()->where('active', true)->orderBy('label')->pluck('label', 'id'),
        ];

        $groups = [];
        foreach ($sources as $alias => $names) {
            $options = [];
            foreach ($names as $id => $name) {
                $options[] = [
                    'value' => $alias . ':' . Sqid::encode(TimeAllocation::TYPES[$alias], (int) $id),
                    'name' => (string) $name,
                ];
            }
            if ($options !== []) {
                $groups[] = ['label' => (string) __('allocation.type.' . $alias), 'options' => $options];
            }
        }

        // P2: freie Mandanten-Dimensionen — je aktiviertem Typ eine Gruppe.
        $today = now();
        $types = \App\Models\TimeDimensionType::query()
            ->where('enabled', true)
            ->with('values')
            ->orderBy('name')
            ->get();
        foreach ($types as $type) {
            $options = [];
            foreach ($type->values as $value) {
                if (! $value->isValidOn($today)) {
                    continue;
                }
                $options[] = [
                    'value' => 'dimension:' . Sqid::encode(\App\Models\TimeDimensionValue::class, (int) $value->id),
                    'name' => (string) $value->name,
                ];
            }
            if ($options !== []) {
                $groups[] = ['label' => (string) $type->name, 'options' => $options];
            }
        }

        return $groups;
    }
}
