<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveDiaryEntryRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Diary;

use App\Enums\Diary\{LocationMode, Mode, Priority};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Rules\IsoCountryCode;
use App\Support\{SqidEncoder, Tz};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\ValueObjects\Duration;
use Illuminate\Validation\Rule;

class SaveDiaryEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    /** Geplante Dauer als Stunden:Minuten, z. B. 1:30 oder 12:00. */
    private const PLANNED_DURATION_PATTERN = '/^\d{1,3}:[0-5]\d$/';

    private ?EntryType $entryType = null;

    private bool $entryTypeResolved = false;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\Platform\User::class,
        'entry_type_id' => \App\Models\Classification\EntryType::class,
        'customer_id' => \App\Models\Customer\Customer::class,
        'assigned_user_id' => \App\Models\Platform\User::class,
        'tour_id' => \App\Models\Diary\Tour::class,
        // Gegenstand des Auftrags (Feature 009; Vollaudit 2026-07, M5).
        'asset_id' => \App\Models\Asset\Asset::class,
        // Folgeauftrag aus offenem Punkt (Feature 139): Prefill-Hidden-Felder.
        'project_id' => \App\Models\Project\Project::class,
        'open_issue_id' => \App\Models\Diary\OpenIssue::class,
    ];

    protected function prepareForValidation(): void {
        // "0" / leerer Wert => null behandeln, damit Folge-Regeln korrekt greifen.
        $typeId = $this->input('entry_type_id');
        if ($typeId === '' || $typeId === '0' || $typeId === 0) {
            $this->merge(['entry_type_id' => null]);
        }
        if ($this->input('tour_id') === '' || $this->input('tour_id') === '0') {
            $this->merge(['tour_id' => null]);
        }
        if ($this->input('customer_id') === '' || $this->input('customer_id') === '0') {
            $this->merge(['customer_id' => null]);
        }
        if ($this->input('assigned_user_id') === '' || $this->input('assigned_user_id') === '0') {
            $this->merge(['assigned_user_id' => null]);
        }
        if ($this->input('asset_id') === '' || $this->input('asset_id') === '0') {
            $this->merge(['asset_id' => null]);
        }
        foreach (['project_id', 'open_issue_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
        if (is_string($this->input('address_country'))) {
            $this->merge(['address_country' => strtoupper((string) $this->input('address_country'))]);
        }

        // Fallback-Modi: leere Werte werden zu Defaults bzw. null normalisiert.
        if ($this->input('mode') === '' || $this->input('mode') === null) {
            $this->merge(['mode' => Mode::Fixed->value]);
        }
        if ($this->input('location_mode') === '' || $this->input('location_mode') === null) {
            $this->merge(['location_mode' => LocationMode::Onsite->value]);
        }
        foreach (['due_date', 'window_start_date', 'window_end_date'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        // datetime-local Von/Bis (Wanduhrzeit) in aktiver Anzeige-Zeitzone → UTC.
        // due_date/window_*_date sind reine Datumsfelder und bleiben unverändert.
        $this->mergeOrgLocalToUtc(['start_at', 'end_at']);

        // Fordert der Typ Einsatzort oder Einsatzdatum, gilt ohne eigene Angabe die
        // Kundenanschrift bzw. der Tag des Beginns, sonst heute (Pilot 2026-11-03).
        $type = $this->resolveEntryType();
        if ($type?->requires_address && $this->isBlank('address_line') && $this->isBlank('address_zip') && $this->isBlank('address_city')) {
            $this->mergeCustomerAddress();
        }
        if ($type?->requires_schedule && $this->isBlank('scheduled_for')) {
            $this->merge(['scheduled_for' => $this->defaultScheduledFor()]);
        }

        // Geplante Dauer (E13) als Stunden:Minuten; leer = ableiten (Zeitfenster/Termin).
        // Ohne das Feld im Request bleibt der gespeicherte Wert unberührt.
        if ($this->has('planned_duration')) {
            $duration = trim((string) $this->input('planned_duration'));
            if ($duration === '') {
                $this->merge(['planned_minutes' => null]);
            } elseif (preg_match(self::PLANNED_DURATION_PATTERN, $duration) === 1) {
                $this->merge(['planned_minutes' => Duration::fromClock($duration)->getTotalMinutes()]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $type = $this->resolveEntryType();
        $requiresCustomer = (bool) ($type?->requires_customer);
        $requiresAddress = (bool) ($type?->requires_address);
        $requiresSchedule = (bool) ($type?->requires_schedule);
        $requiresTour = (bool) ($type?->requires_tour);

        $mode = (string) $this->input('mode', Mode::Fixed->value);
        // 'recurring' wird nur vom Generator vergeben; manuell wählbar sind
        // fixed/deadline/window/backlog.
        $startRequired = $mode === Mode::Fixed->value;
        $dueRequired = $mode === Mode::Deadline->value;
        $windowRequired = $mode === Mode::Window->value;

        return [
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3,4,5,6,7,8'],
            'start_at' => [$startRequired ? 'required' : 'nullable', 'date'],
            'end_at' => [$startRequired ? 'required' : 'nullable', 'date', 'after_or_equal:start_at'],

            'mode' => ['required', Rule::enum(Mode::class)],
            'due_date' => [$dueRequired ? 'required' : 'nullable', 'date'],
            'window_start_date' => [$windowRequired ? 'required' : 'nullable', 'date'],
            'window_end_date' => [$windowRequired ? 'required' : 'nullable', 'date', 'after_or_equal:window_start_date'],
            'location_mode' => ['required', Rule::enum(LocationMode::class)],
            'user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],

            // Phase 6: typgesteuerte Felder
            'entry_type_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('entry_types')],
            'title' => ['nullable', 'string', 'max:200'],
            'priority' => ['nullable', Rule::enum(Priority::class)],
            'customer_id' => [$requiresCustomer ? 'required' : 'nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'assigned_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            // Gegenstand des Auftrags (Feature 009; Vollaudit 2026-07, M5).
            'asset_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('assets')],
            'open_issue_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('open_issues')],

            'scheduled_for' => [$requiresSchedule ? 'required' : 'nullable', 'date'],
            'time_window_start' => ['nullable', 'date_format:H:i'],
            'time_window_end' => ['nullable', 'date_format:H:i', 'after_or_equal:time_window_start'],
            'service_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'planned_duration' => ['nullable', 'string', 'regex:' . self::PLANNED_DURATION_PATTERN],
            'planned_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],

            'address_line' => [$requiresAddress ? 'required' : 'nullable', 'string', 'max:200'],
            'address_zip' => ['nullable', 'string', 'max:16'],
            'address_city' => [$requiresAddress ? 'required' : 'nullable', 'string', 'max:120'],
            'address_country' => ['nullable', 'string', 'max:2', new IsoCountryCode],
            'address_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address_lng' => ['nullable', 'numeric', 'between:-180,180'],

            'tour_id' => [$requiresTour ? 'required' : 'nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tours')],
            'tour_position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'planned_duration.regex' => (string) __('diary.planned_duration.format'),
            'planned_minutes.min' => (string) __('diary.planned_duration.range'),
            'planned_minutes.max' => (string) __('diary.planned_duration.range'),
        ];
    }

    private function resolveEntryType(): ?EntryType {
        if (! $this->entryTypeResolved) {
            $id = $this->decodeId(EntryType::class, $this->input('entry_type_id'));
            $this->entryType = $id !== null ? EntryType::query()->find($id) : null;
            $this->entryTypeResolved = true;
        }

        return $this->entryType;
    }

    /**
     * prepareForValidation() und rules() laufen VOR der Sqid-Dekodierung in validationData(): Formulare senden Sqids
     * (nur Altbestand/intern numerische IDs). Ohne Dekodierung wären Typ und Kunde hier nicht auffindbar.
     *
     * @param class-string $class
     */
    private function decodeId(string $class, mixed $raw): ?int {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $id = ctype_digit($raw) ? (int) $raw : app(SqidEncoder::class)->decode($class, $raw);

        return $id !== null && $id > 0 ? $id : null;
    }

    private function isBlank(string $key): bool {
        $value = $this->input($key);

        return ! is_string($value) || trim($value) === '';
    }

    /** Einsatzort aus der Kundenanschrift; Koordinaten nur als Paar, ein fremder Kunde fällt am Scope durch. */
    private function mergeCustomerAddress(): void {
        $customerId = $this->decodeId(Customer::class, $this->input('customer_id'));
        $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
        if (! $customer instanceof Customer || StringHelper::trimToNull($customer->address_street) === null) {
            return;
        }
        $country = is_string($customer->country) && strlen($customer->country) === 2 ? strtoupper($customer->country) : null;
        $hasCoordinates = $customer->address_lat !== null && $customer->address_lng !== null;

        $this->merge([
            'address_line' => $customer->address_street,
            'address_zip' => $customer->address_zip,
            'address_city' => $customer->address_city,
            'address_country' => $country,
            'address_lat' => $hasCoordinates ? $customer->address_lat : null,
            'address_lng' => $hasCoordinates ? $customer->address_lng : null,
        ]);
    }

    /** Tag des Beginns (Ortszeit) bei festem Zeitraum, sonst heute. */
    private function defaultScheduledFor(): string {
        $start = $this->input('start_at');
        if ($this->input('mode') === Mode::Fixed->value && is_string($start) && trim($start) !== '') {
            try {
                // start_at liegt nach mergeOrgLocalToUtc() in UTC; das Einsatzdatum ist der Ortstag.
                return CarbonImmutable::parse(trim($start), 'UTC')->setTimezone(Tz::current())->toDateString();
            } catch (\Throwable) {
                // Ungültiger Beginn: die 'date'-Regel meldet ihn.
            }
        }

        return Tz::now()->toDateString();
    }
}
