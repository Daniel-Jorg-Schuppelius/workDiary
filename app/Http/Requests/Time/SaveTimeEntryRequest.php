<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Time;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ParsesOrgLocalDateTimes;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'task_id' => \App\Models\Project\Task::class,
        'diary_entry_id' => \App\Models\Diary\DiaryEntry::class,
        'service_ticket_id' => \App\Models\ServiceTicket\ServiceTicket::class,
        'rework_reason_classification_id' => \App\Models\Classification\Classification::class,
        'goodwill_reason_classification_id' => \App\Models\Classification\Classification::class,
        'tag_ids' => \App\Models\Classification\Tag::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        // Range-Modus: Von/Bis sind vorhanden → date/minutes optional, weil
        // der Model-Hook sie aus started_at/ended_at − break_minutes ableitet.
        $isRange = $this->filled('started_at') && $this->filled('ended_at');
        $kind = $this->kind();

        return [
            // Kein Anlegen in einem freigegebenen Monat (Sicherheitsscan
            // 2026-08-23, S-32) — dafür gibt es den Zeitkorrektur-Antrag.
            'date' => [$isRange ? 'nullable' : 'required', 'date', new \App\Rules\NotInLockedMonth($this->targetUser())],
            'kind' => ['required', Rule::enum(TimeEntryKind::class)],
            // Je Art begrenzt (24 h je Person, 31 Tage je Gerätelaufzeit, Feature 193) —
            // sonst ließen sich Personenzeiten über Tage buchen.
            'minutes' => [$isRange ? 'nullable' : 'required', 'integer', 'min:1', 'max:' . $kind->maxMinutes()],
            // Zeitraum-Modus ohne `date`: der Tag folgt aus dem Beginn.
            'started_at' => ['nullable', 'date', new \App\Rules\NotInLockedMonth($this->targetUser())],
            // Zeitraum mit derselben Grenze wie der Dauer-Modus.
            'ended_at' => array_filter(['nullable', 'date', 'after:started_at', $this->rangeLimit($kind)]),
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
            'service_ticket_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('service_tickets')],
            'description' => ['nullable', 'string', 'max:500'],
            // Anfahrtspauschale (Feature 098): leer = Automatik aus der Kondition.
            'billing_travel_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            // Rang 59: Nacharbeit-/Kulanz-Kennzeichnung (Klassifikations-Domänen).
            // Org-Constraint: eigene Org ODER Plattform-Default (organization_id NULL).
            'rework_reason_classification_id' => ['nullable', 'integer', Rule::exists('classifications', 'id')->where(fn($q) => $this->scopeClassification($q, 'rework_reason'))],
            'goodwill_reason_classification_id' => ['nullable', 'integer', Rule::exists('classifications', 'id')->where(fn($q) => $this->scopeClassification($q, 'goodwill_reason'))],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('tags')],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Domänen- + Org-Filter für Klassifikations-Referenzen: eigene Org
     * oder Plattform-Default (organization_id NULL); ohne Org-Bindung
     * (CLI) nur Domänen-Filter.
     *
     * @param \Illuminate\Database\Query\Builder $q
     */
    private function scopeClassification($q, string $domain): void {
        $q->where('domain', $domain);
        $orgId = app()->bound('currentOrganization') ? (app('currentOrganization')->id ?? null) : null;
        if ($orgId !== null) {
            $q->where(fn($qq) => $qq->where('organization_id', $orgId)->orWhereNull('organization_id'));
        }
    }

    protected function prepareForValidation(): void {
        foreach (['task_id', 'diary_entry_id', 'service_ticket_id', 'started_at', 'ended_at', 'rework_reason_classification_id', 'goodwill_reason_classification_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('break_minutes') === '') {
            $this->merge(['break_minutes' => null]);
        }
        // Ohne Angabe bleibt die Art des Eintrags, neu ist Arbeit — nie NULL in die Spalte.
        if ($this->input('kind') === null || $this->input('kind') === '') {
            $entry = $this->route('time_entry');
            $this->merge(['kind' => $entry instanceof \App\Models\Time\TimeEntry ? $entry->kind->value : TimeEntryKind::Work->value]);
        }

        // Die datetime-local-Eingaben (Wanduhrzeit ohne Zeitzone) werden in der
        // aktiven Anzeige-Zeitzone interpretiert und zur Speicherung nach UTC
        // umgerechnet. Vollaudit 2026-07 (N2): über das gemeinsame Bauteil —
        // ungültige Eingaben meldet die 'date'-Regel statt eines 500ers.
        $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
    }

    /** @return array<string, string> */
    public function attributes(): array {
        return [
            'date' => __('Datum'),
            'kind' => __('Art'),
            'minutes' => __('Dauer'),
            'started_at' => __('Von'),
            'ended_at' => __('Bis'),
            'break_minutes' => __('Pause'),
            'description' => __('Beschreibung'),
            'new_tags' => __('Tags'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        $limit = $this->kind() === TimeEntryKind::Machine
            ? __('Eine Gerätelaufzeit darf höchstens 31 Tage umfassen.')
            : __('Ein Zeiteintrag darf höchstens 24 Stunden umfassen.');

        return [
            'ended_at.after' => __('„Bis" muss nach „Von" liegen.'),
            'ended_at.before_or_equal' => $limit,
            'minutes.max' => $limit,
        ];
    }

    private function kind(): TimeEntryKind {
        $value = $this->input('kind');

        return (is_string($value) ? TimeEntryKind::tryFrom($value) : null) ?? TimeEntryKind::Work;
    }

    private function rangeLimit(TimeEntryKind $kind): ?string {
        $start = $this->input('started_at');
        if (! is_string($start) || $start === '' || ! $this->filled('ended_at')) {
            return null;
        }
        try {
            return 'before_or_equal:' . \Carbon\CarbonImmutable::parse($start)->addMinutes($kind->maxMinutes())->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Wessen Monatssperre gilt: beim Bearbeiten die des Eigentümers, beim
     * Anlegen die des Angemeldeten — der Controller bucht immer auf ihn. Eine
     * `user_id` aus der Eingabe zählt nicht, sonst prüfte die Regel einen
     * fremden Monat (Vollscan 2026-10-21).
     */
    private function targetUser(): ?\App\Models\Platform\User {
        $entry = $this->route('time_entry');

        if ($entry instanceof \App\Models\Time\TimeEntry && $entry->user instanceof \App\Models\Platform\User) {
            return $entry->user;
        }

        $auth = \Illuminate\Support\Facades\Auth::user();

        return $auth instanceof \App\Models\Platform\User ? $auth : null;
    }

}
