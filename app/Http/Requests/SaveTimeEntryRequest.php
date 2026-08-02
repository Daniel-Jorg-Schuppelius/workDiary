<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTimeEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ParsesOrgLocalDateTimes;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'task_id' => \App\Models\Task::class,
        'diary_entry_id' => \App\Models\DiaryEntry::class,
        'rework_reason_classification_id' => \App\Models\Classification::class,
        'goodwill_reason_classification_id' => \App\Models\Classification::class,
        'tag_ids' => \App\Models\Tag::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        // Range-Modus: Von/Bis sind vorhanden → date/minutes optional, weil
        // der Model-Hook sie aus started_at/ended_at − break_minutes ableitet.
        $isRange = $this->filled('started_at') && $this->filled('ended_at');

        return [
            'date' => [$isRange ? 'nullable' : 'required', 'date'],
            'minutes' => [$isRange ? 'nullable' : 'required', 'integer', 'min:1', 'max:1440'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after:started_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
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
        foreach (['task_id', 'diary_entry_id', 'started_at', 'ended_at', 'rework_reason_classification_id', 'goodwill_reason_classification_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('break_minutes') === '') {
            $this->merge(['break_minutes' => null]);
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
        return [
            'ended_at.after' => __('„Bis" muss nach „Von" liegen.'),
        ];
    }
}
