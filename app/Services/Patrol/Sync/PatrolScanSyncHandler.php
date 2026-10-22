<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolScanSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Patrol\Sync;

use App\Enums\User\Permission;
use App\Models\Patrol\PatrolRun;
use App\Models\Platform\User;
use App\Services\Patrol\PatrolService;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Offline erfasster Kontrollpunkt (MVP-1291): gewertet wird die Scanzeit des
 * Geräts, nicht die Ankunft der Nachlieferung.
 */
final class PatrolScanSyncHandler implements SyncCommandHandler {
    public function __construct(private readonly PatrolService $patrols) {}

    /** @return list<string> */
    public function types(): array {
        return ['patrol.scan'];
    }

    public function handle(User $user, string $type, array $payload): string {
        $data = Validator::make($payload, [
            'run' => ['required', 'string', 'max:64'],
            'token' => ['required', 'string', 'max:64'],
            'at' => ['nullable', 'date'],
        ])->validate();

        if (! $user->can(Permission::DispatchViewAny->value)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Rundgänge.'));
        }
        $runId = Sqid::decodeOrNumeric(PatrolRun::class, (string) $data['run']);
        $run = $runId !== null ? PatrolRun::query()->where('organization_id', $user->organization_id)->find($runId) : null;
        if (! $run instanceof PatrolRun) {
            throw new RuntimeException((string) __('Rundgang nicht gefunden.'));
        }

        $checkpoint = $this->patrols->scan($run, (string) $data['token'], isset($data['at']) ? CarbonImmutable::parse((string) $data['at']) : null);

        return 'patrol_checkpoints:' . $checkpoint->id;
    }
}
