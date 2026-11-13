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

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Diary\{LocationMode, Mode, Priority, Status};
use App\Enums\Period\PeriodKind;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Models\Classification\EntryType;
use App\Models\Customer\Customer;
use App\Rules\IsoCountryCode;
use App\Services\Classification\ClassificationResolver;
use App\Support\{OrganizationContext, Tz};
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\ValueObjects\Duration;
use Illuminate\Validation\Rule;

class SaveDiaryEntryRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    /** Geplante Dauer als Stunden:Minuten, z. B. 1:30 oder 12:00. */
    private const string PLANNED_DURATION_PATTERN = '/^\d{1,3}:[0-5]\d$/';

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
        // Benannte prüfende Person (Feature 198).
        'reviewer_user_id' => \App\Models\Platform\User::class,
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
        // Periode und Prüfung (Feature 198): Schlüssel kanonisch, Leerwerte als null.
        foreach (['reviewer_user_id', 'product_group'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === '0') {
                $this->merge([$key => null]);
            }
        }
        if ($this->has('period_key')) {
            $period = StringHelper::trimToNull(is_string($this->input('period_key')) ? $this->input('period_key') : null);
            $this->merge(['period_key' => $period !== null ? (PeriodKey::tryParse($period)?->key() ?? $period) : null]);
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
            // Der Status kommt nur als Rundreise aus dem Formular; gesetzt wird er im Lebenszyklus.
            'status' => ['required', Rule::enum(Status::class)],
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

            // Periode (Feature 198, MVP-1634): Pflicht beim Anlegen, wenn die Eintragsart sie verlangt.
            'period_key' => [$this->periodRequired($type) ? 'required' : 'nullable', 'string', 'max:10', $this->periodRule($type)],
            'product_group' => ['nullable', 'string', 'max:64', $this->productGroupRule()],
            'reviewer_user_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
        ];
    }

    /** Pflicht nur beim Anlegen: Bestand ohne Periode bleibt bearbeitbar. */
    private function periodRequired(?EntryType $type): bool {
        return $type !== null && $type->requiredPeriodKinds() !== [] && $this->route('diary') === null;
    }

    /** Gültiger Schlüssel; verlangt die Eintragsart Periodenarten, muss er zu einer davon passen. */
    private function periodRule(?EntryType $type): \Closure {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($type): void {
            $period = is_string($value) ? PeriodKey::tryParse($value) : null;
            if ($period === null) {
                $fail((string) __('diary.period.invalid'));

                return;
            }
            $kinds = $type?->requiredPeriodKinds() ?? [];
            if ($kinds !== [] && ! in_array($period->kind, $kinds, true)) {
                $fail((string) __('diary.period.kind_mismatch', [
                    'kinds' => implode(', ', array_map(static fn (PeriodKind $kind): string => $kind->label(), $kinds)),
                ]));
            }
        };
    }

    /** Produktgruppe nur aus den aktiven Klassifikationen der Organisation. */
    private function productGroupRule(): \Closure {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            $exists = is_string($value) && app(ClassificationResolver::class)
                ->resolveCode(OrganizationContext::currentId(), ClassificationDomain::ProductGroup, $value) !== null;
            if (! $exists) {
                $fail((string) __('diary.product_group.invalid'));
            }
        };
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
            $id = $this->decodedInputId('entry_type_id', EntryType::class);
            $this->entryType = $id !== null ? EntryType::query()->find($id) : null;
            $this->entryTypeResolved = true;
        }

        return $this->entryType;
    }

    private function isBlank(string $key): bool {
        $value = $this->input($key);

        return ! is_string($value) || trim($value) === '';
    }

    /** Einsatzort aus der Kundenanschrift; Koordinaten nur als Paar, ein fremder Kunde fällt am Scope durch. */
    private function mergeCustomerAddress(): void {
        $customerId = $this->decodedInputId('customer_id', Customer::class);
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
