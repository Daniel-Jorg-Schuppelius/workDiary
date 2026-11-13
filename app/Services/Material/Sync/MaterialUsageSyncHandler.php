<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialUsageSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Material\Sync;

use App\Exceptions\MaterialStockException;
use App\Http\Requests\Material\SaveDayMaterialUsageRequest;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Material\MaterialUsageRecorder;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Services\Sync\{SyncConflictException, SyncFormRequest};
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Offline erfasster Materialverbrauch von „Heute“ (MVP-1594), Typ
 * `material.usage`. Dieselben Regeln und derselbe Dienst wie online; die
 * client_uuid des Befehls verhindert die zweite Zeile beim erneuten Senden.
 * Fehlt eine Pflichtangabe, ist das ein Konflikt — der Nutzer ergänzt sie.
 * Verhindert eine Lagerregel die Bestandsbuchung (Bestand, Charge,
 * Seriennummer, Einheit; MVP-1679), ebenso: Konflikt statt stiller Buchung.
 * Fehlt nur Bestand, sagt `shortage` dem Konfliktformular, dass „Negativen
 * Bestand zulassen“ ihn auflösen kann (MVP-1691); Recht und Vier-Augen prüft
 * beim erneuten Senden die Lagerbuchung wie online.
 */
final class MaterialUsageSyncHandler implements SyncCommandHandler {
    private const array REQUIRED = ['project_id', 'date', 'description', 'quantity', 'unit'];

    public function __construct(private readonly MaterialUsageRecorder $recorder) {}

    /** @return list<string> */
    public function types(): array {
        return ['material.usage'];
    }

    public function handle(User $user, string $type, array $payload): string {
        if ($type !== 'material.usage') {
            throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type);
        }

        $missing = [];
        foreach (self::REQUIRED as $field) {
            if (blank($payload[$field] ?? null)) {
                $missing[] = (string) __('validation.attributes.' . $field);
            }
        }
        if ($missing !== []) {
            throw new SyncConflictException(
                (string) __('sync.conflict.missing_fields', ['fields' => implode(', ', $missing)]),
                ['missing' => implode(', ', $missing)],
                '',
            );
        }

        $data = SyncFormRequest::validate(SaveDayMaterialUsageRequest::class, $payload, $user)->validated();
        $project = Project::query()->findOrFail((int) $data['project_id']);

        try {
            $usage = $this->recorder->recordForDay($user, $project, CarbonImmutable::parse((string) $data['date']), Arr::except($data, ['project_id', 'date']));
        } catch (MaterialStockException $e) {
            $state = ['field' => $e->field(), 'stock' => $e->reason()];
            if ($e->isShortage()) {
                $state['shortage'] = true;
            }

            throw new SyncConflictException($e->reason(), $state, '');
        }

        return 'material_usages:' . $usage->id;
    }
}
