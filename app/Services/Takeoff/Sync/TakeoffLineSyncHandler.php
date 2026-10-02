<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffLineSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff\Sync;

use App\Enums\Takeoff\TakeoffFormula;
use App\Models\Platform\User;
use App\Models\Takeoff\Takeoff;
use App\Services\Attachments\FileAttacher;
use App\Services\Sync\Contracts\{SyncAttachmentTarget, SyncCommandHandler};
use App\Services\Takeoff\TakeoffService;
use App\Support\Sqid;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Gate, Validator};
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Offline erfasste Aufmaßzeile (MVP-1059), Typ `takeoff.line`; Fotos dazu
 * kommen nach dem Abgleich über den Anhangsweg des Offline-Syncs ans Blatt.
 */
final class TakeoffLineSyncHandler implements SyncAttachmentTarget, SyncCommandHandler {
    public function __construct(private readonly TakeoffService $takeoffs) {}

    /** @return list<string> */
    public function types(): array {
        return ['takeoff.line'];
    }

    public function handle(User $user, string $type, array $payload): string {
        if ($type !== 'takeoff.line') {
            throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type);
        }
        $data = Validator::make($payload, [
            'takeoff' => ['required', 'string'],
            'formula' => ['required', Rule::enum(TakeoffFormula::class)],
            'values' => ['required', 'array', 'max:20'],
            'values.*' => ['nullable', 'string', 'max:500'],
            'factor' => ['nullable', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:16'],
        ])->validate();
        $takeoff = $this->takeoff($data['takeoff']);
        if (! Gate::forUser($user)->allows('update', $takeoff)) {
            throw new RuntimeException((string) __('takeoff.error.locked'));
        }
        $line = $this->takeoffs->saveLine($takeoff, TakeoffService::lineInput($data));

        return 'takeoff_lines:' . $line->id;
    }

    public function attach(User $user, string $type, string $resultRef, string $field, UploadedFile $file): bool {
        $lineId = (int) str_replace('takeoff_lines:', '', $resultRef);
        $takeoff = Takeoff::query()->whereHas('lines', fn ($q) => $q->whereKey($lineId))->first();
        if ($takeoff === null || ! Gate::forUser($user)->allows('view', $takeoff)) {
            return false;
        }
        app(FileAttacher::class)->store($takeoff, $file, $user->id);

        return true;
    }

    private function takeoff(string $sqid): Takeoff {
        $takeoff = Takeoff::query()->whereKey(Sqid::decode(Takeoff::class, $sqid))->first();
        if ($takeoff === null) {
            throw new RuntimeException((string) __('takeoff.error.not_found'));
        }

        return $takeoff;
    }
}
