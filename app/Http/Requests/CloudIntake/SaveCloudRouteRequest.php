<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveCloudRouteRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeRouteTarget;
use App\Enums\Document\DocumentType;
use App\Http\Requests\BaseFormRequest;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};
use App\Services\CloudIntake\{CloudRouteTargets, CloudRouteTestResult, CloudRouteTester, RoutePatternValidator};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\{Rule, Validator};

/**
 * Ordnerregel anlegen/bearbeiten (Feature 080, MVP-358/1613): Pfadmuster über
 * den {@see RoutePatternValidator}, danach der Regeltest mit dem Testpfad
 * ({@see CloudRouteTester}) — ohne bestandenen Test wird nicht gespeichert,
 * eine Überschneidung mit einer anderen aktiven Regel blockiert die
 * Aktivierung. Berechtigung trägt der Controller (`cloudIntake.route.manage`).
 */
class SaveCloudRouteRequest extends BaseFormRequest {
    private ?CloudRouteTestResult $testResult = null;

    private ?Model $targetRef = null;

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'path_pattern' => ['required', 'string', 'max:512'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'target' => ['required', Rule::enum(CloudIntakeRouteTarget::class)],
            'document_type' => ['nullable', 'required_if:target,document', Rule::enum(DocumentType::class)],
            'target_ref' => ['nullable', 'string', 'max:80'],
            'test_path' => ['required', 'string', 'max:1024'],
            'allowed_extensions' => ['nullable', 'string', 'max:300'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
            'auto_version' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $validator): void {
            $errors = app(RoutePatternValidator::class)->validatePattern((string) $this->input('path_pattern', ''));
            foreach ($errors as $error) {
                $validator->errors()->add('path_pattern', $error);
            }
            $connection = $this->connection();
            if ($validator->errors()->isNotEmpty() || $connection === null) {
                return;
            }

            $data = $validator->getData();
            $rawRef = trim((string) ($data['target_ref'] ?? ''));
            if ($rawRef !== '' && ($data['target'] ?? null) === CloudIntakeRouteTarget::Document->value) {
                $this->targetRef = app(CloudRouteTargets::class)->parse($rawRef, (int) $connection->organization_id);
                if ($this->targetRef === null) {
                    $validator->errors()->add('target_ref', (string) __('cloud_intake.validation.target_ref_invalid'));

                    return;
                }
            }

            $candidate = $this->candidate($connection, $data);
            $this->testResult = app(CloudRouteTester::class)->test($connection, $candidate, (string) ($data['test_path'] ?? ''));
            if (! $this->testResult->ok) {
                $validator->errors()->add($this->testResult->field ?? 'test_path', $this->testResult->summary);
            }
        });
    }

    /** Ergebnis des bestandenen Regeltests für die Rückmeldung. */
    public function testSummary(): string {
        return $this->testResult->summary ?? '';
    }

    /**
     * Normalisierte Attribute fürs Modell (Endungs-Liste aus Freitext).
     *
     * @return array<string, mixed>
     */
    public function routeAttributes(): array {
        $document = $this->validated('target') === CloudIntakeRouteTarget::Document->value;

        return [
            'path_pattern' => trim((string) $this->validated('path_pattern'), "/ \t"),
            'priority' => (int) $this->validated('priority'),
            'target' => $this->validated('target'),
            'document_type' => $this->validated('document_type'),
            'allowed_extensions' => $this->extensions((string) $this->validated('allowed_extensions', '')),
            'max_file_size' => $this->validated('max_file_size'),
            'auto_version' => (bool) $this->validated('auto_version', false),
            'active' => (bool) $this->validated('active', true),
            'target_ref_type' => $document ? $this->targetRef?->getMorphClass() : null,
            'target_ref_id' => $document ? $this->targetRef?->getKey() : null,
            'test_path' => trim((string) $this->validated('test_path')),
            'tested_at' => Carbon::now(),
        ];
    }

    private function connection(): ?CloudDocumentConnection {
        $connection = $this->route('connection');
        if ($connection instanceof CloudDocumentConnection) {
            return $connection;
        }
        $route = $this->route('cloudRoute');

        return $route instanceof CloudDocumentRoute ? $route->connection : null;
    }

    /**
     * Regel mit den eingegebenen Werten, ungespeichert — der Test sieht so
     * aus wie die spätere Übernahme.
     *
     * @param  array<string, mixed>  $data
     */
    private function candidate(CloudDocumentConnection $connection, array $data): CloudDocumentRoute {
        $existing = $this->route('cloudRoute');
        $candidate = $existing instanceof CloudDocumentRoute ? $existing->replicate() : new CloudDocumentRoute;
        if ($existing instanceof CloudDocumentRoute) {
            $candidate->setAttribute($candidate->getKeyName(), $existing->getKey());
            $candidate->exists = true;
        }
        $candidate->forceFill([
            'connection_id' => $connection->id,
            'path_pattern' => trim((string) ($data['path_pattern'] ?? ''), "/ \t"),
            'priority' => (int) ($data['priority'] ?? 100),
            'target' => CloudIntakeRouteTarget::from((string) $data['target']),
            'document_type' => $data['document_type'] ?? null,
            'allowed_extensions' => $this->extensions((string) ($data['allowed_extensions'] ?? '')),
            'active' => (bool) ($data['active'] ?? true),
            'target_ref_type' => $this->targetRef?->getMorphClass(),
            'target_ref_id' => $this->targetRef?->getKey(),
        ]);

        return $candidate;
    }

    /** @return list<string>|null */
    private function extensions(string $raw): ?array {
        $extensions = array_values(array_filter(array_map(
            static fn (string $e): string => strtolower(trim($e, ". \t")),
            preg_split('/[\s,;]+/', $raw) ?: [],
        ), static fn (string $e): bool => $e !== ''));

        return $extensions === [] ? null : $extensions;
    }
}
