<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncAttachmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Integration;

use App\Enums\Sync\SyncCommandStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration\SyncCommand;
use App\Services\Attachments\FileAttacher;
use App\Services\Sync\Contracts\SyncAttachmentTarget;
use App\Services\Sync\SyncCommandService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\UploadedFile;

/**
 * Nachreichen der Offline-Foto-Queue (Feature 035 Phase 3; Audit 2026-08,
 * W4.1). Fotos passen nicht in den JSON-Batch der Outbox — sie kommen einzeln
 * als Multipart nach, sobald das Gerät wieder online ist.
 *
 * Die Zuordnung läuft über die `client_uuid` des bereits angewendeten
 * Befehls: nur wer den Befehl selbst abgesetzt hat, kennt sie, und der
 * Sync-Eintrag ist bereits auf den Nutzer festgeschrieben. Die Datei nimmt der
 * Handler des Befehlstyps an, sofern er {@see SyncAttachmentTarget} ist
 * (Formularabgabe, Aufmaßzeile — MVP-1059).
 */
class SyncAttachmentController extends Controller {
    public function __invoke(Request $request, SyncCommandService $sync): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $data = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'field' => ['required', 'string', 'max:64'],
            'file' => array_merge(['required'], FileAttacher::rule()),
        ]);

        $command = SyncCommand::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $data['client_uuid'])
            ->first();
        $target = $command !== null ? $sync->handlerFor((string) $command->type) : null;
        if (! $target instanceof SyncAttachmentTarget) {
            return response()->json(['status' => 'pending'], 409);
        }

        if ($command->result_status !== SyncCommandStatus::Applied) {
            // Noch nicht angewendet (oder abgelehnt): der Client darf es
            // später erneut versuchen bzw. das Foto verwerfen.
            return response()->json(['status' => 'pending'], 409);
        }

        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);

        if (! $target->attach($user, (string) $command->type, (string) $command->result_ref, (string) $data['field'], $file)) {
            return response()->json(['status' => 'gone'], 410);
        }

        return response()->json(['status' => 'stored']);
    }
}
