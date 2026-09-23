<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveProjectRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Project\ProjectStatus;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{Customer, ForeignCustomer, Project, Team, User};
use Closure;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => Customer::class,
        'foreign_customer_id' => ForeignCustomer::class,
        'parent_id' => Project::class,
        'team_ids' => Team::class,
        'member_ids' => User::class,
    ];

    /**
     * Leere Tri-State-Overrides (Auswahl „Erben") → null, damit `nullable`/`in:0,1`
     * greift und die Spalte auf null (= vererben) gesetzt wird.
     */
    protected function prepareForValidation(): void {
        foreach (['weather_auto_fetch', 'billable'] as $triState) {
            if ($this->input($triState) === '') {
                $this->merge([$triState => null]);
            }
        }

        // Schlüsselwörter (MVP-483) kommen als eine Zeile aus dem Formular und
        // werden hier zur normalisierten Liste — der Matcher vergleicht später
        // kleingeschrieben, kurze Begriffe wären nur Zufallstreffer.
        $keywords = $this->input('keywords');
        if (is_string($keywords)) {
            $tokens = [];
            foreach (preg_split('/[,;\r\n]+/', $keywords) ?: [] as $token) {
                $token = mb_strtolower(StringHelper::normalizeWhitespace(trim($token)));
                if (mb_strlen($token) >= 3) {
                    $tokens[$token] = true;
                }
            }
            $this->merge(['keywords' => $tokens === [] ? null : array_slice(array_keys($tokens), 0, 20)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        /** @var Project|null $project */
        $project = $this->route('project');

        // Dekodierte Werte (Sqid → int) verwenden, damit der Vergleich mit dem
        // ebenfalls dekodierten foreign_customer_id konsistent ist.
        $customerId = $this->validationData()['customer_id'] ?? $project?->customer_id;
        $customerId = ($customerId === '' || $customerId === null) ? null : (int) $customerId;

        // Namens-Eindeutigkeit gilt je (Kunde, Fremdkunde): gleichnamige Projekte
        // verschiedener Endkunden derselben Firma sind legitim (Toggl-Import
        // „Als ein Kunde"). Zusätzlich org-scopen — kundenlose (interne) Projekte
        // dürfen nicht gegen fremde Mandanten geprüft werden.
        $foreignCustomerId = $this->validationData()['foreign_customer_id'] ?? $project?->foreign_customer_id;
        $foreignCustomerId = ($foreignCustomerId === '' || $foreignCustomerId === null) ? null : (int) $foreignCustomerId;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('projects', 'name')
                    ->ignore($project?->id)
                    ->where(fn($query) => $query
                        ->where('organization_id', $this->user()?->organization_id)
                        ->where('customer_id', $customerId)
                        ->where('foreign_customer_id', $foreignCustomerId)),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            // Synonyme für die Schlüsselwort-Zuordnung importierter Zeiten;
            // der Projektname selbst wird ohnehin abgeleitet.
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'min:3', 'max:60'],
            'color' => ['nullable', 'string', 'max:16'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_default' => ['sometimes', 'boolean'],
            // Wetter-Auto-Abruf-Override (Feature 062, Rang 12): Tri-State
            // ''=erben (→ null in prepareForValidation), '1'=an, '0'=aus.
            'weather_auto_fetch' => ['nullable', 'in:0,1'],
            // Abrechenbar-Override: Tri-State wie weather_auto_fetch; null =
            // erben (Parent-Kette → Kunde), s. effectiveBillable().
            'billable' => ['nullable', 'in:0,1'],
            'team_ids' => ['array'],
            'team_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization('teams')],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', new \App\Rules\ExistsInCurrentOrganization()],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'foreign_customer_id' => [
                'nullable',
                'integer',
                new \App\Rules\ExistsInCurrentOrganization('foreign_customers'),
                function (string $attribute, mixed $value, Closure $fail) use ($customerId): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if ($customerId === null) {
                        $fail(__('Ein Fremdkunde kann nur einem Projekt mit Kunde zugeordnet werden.'));

                        return;
                    }
                    $foreignCustomer = ForeignCustomer::query()->find((int) $value);
                    if ($foreignCustomer === null) {
                        return;
                    }
                    if ((int) $foreignCustomer->customer_id !== (int) $customerId) {
                        $fail(__('Der gewählte Fremdkunde gehört nicht zum gewählten Kunden.'));
                    }
                },
            ],
            'parent_id' => [
                'nullable',
                'integer',
                new \App\Rules\ExistsInCurrentOrganization('projects'),
                function (string $attribute, mixed $value, Closure $fail) use ($project): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $parentId = (int) $value;
                    if ($project !== null && $parentId === (int) $project->id) {
                        $fail(__('Ein Projekt kann nicht sein eigenes Übergeordnetes Projekt sein.'));

                        return;
                    }
                    $parent = Project::query()->find($parentId);
                    if ($parent === null) {
                        $fail(__('Übergeordnetes Projekt nicht gefunden.'));

                        return;
                    }
                    if ($project !== null && $project->isAncestorOf($parent)) {
                        $fail(__('Zyklus: das gewählte Übergeordnete Projekt ist ein Sub-Projekt dieses Projekts.'));
                    }
                },
            ],
        ];
    }
}
