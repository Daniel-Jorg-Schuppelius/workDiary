<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphAvailabilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Msgraph\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{MsgraphConnection, User};
use App\Plugins\Msgraph\Api\MsgraphCalendarClient;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Free/Busy-Abfrage für den Termin-Dialog (Feature 102, C2): löst die
 * gewählten Teilnehmer (Sqids, org-gescopt) zu E-Mail-Adressen auf und fragt
 * `getSchedule` über die Kalender-Verbindung der Organisation ab. Antwortet
 * nur mit free/busy/unknown — nie mit Termindetails Dritter.
 */
class MsgraphAvailabilityController extends Controller {
    public function __invoke(Request $request): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $user->organization_id !== null, 403);

        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'users' => ['required', 'array', 'max:50'],
            'users.*' => ['string'],
        ]);

        $connection = MsgraphConnection::query()
            ->where('organization_id', $user->organization_id)
            ->first();
        if (! $connection instanceof MsgraphConnection || ! $connection->isActive()) {
            return response()->json(['message' => __('msgraph.availability.no_connection')], 409);
        }

        /** @var list<string> $userSqids */
        $userSqids = $data['users'];
        $ids = collect($userSqids)
            ->map(fn (string $sqid): ?int => Sqid::decodeOrNumeric(User::class, $sqid))
            ->filter()
            ->unique()
            ->values();

        $participants = User::query()
            ->where('organization_id', $user->organization_id)
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'email']);

        $byEmail = $participants->keyBy(fn (User $u): string => strtolower((string) $u->email));

        try {
            $statuses = (new MsgraphCalendarClient($connection))->freeBusy(
                array_values($byEmail->keys()->all()),
                Carbon::parse((string) $data['start'])->utc(),
                Carbon::parse((string) $data['end'])->utc(),
            );
        } catch (Throwable) {
            return response()->json(['message' => __('msgraph.availability.failed')], 502);
        }

        $results = [];
        foreach ($byEmail as $email => $participant) {
            $results[] = [
                'name' => (string) $participant->name,
                'status' => $statuses[$email] ?? 'unknown',
            ];
        }

        return response()->json(['results' => $results]);
    }
}
