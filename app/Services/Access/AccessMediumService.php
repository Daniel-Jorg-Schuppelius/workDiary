<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessMediumService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Access;

use App\Enums\Access\AccessMediumStatus;
use App\Models\{AccessMedium, AccessMediumHandover, Task, User};
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Zutrittsmedien-Verwaltung (Feature 092, Stufe 1, MVP-657–659).
 *
 * Der Kern ist die Verlust-Disziplin: **Eine Verlustmeldung hinterlässt
 * zwingend eine Sperr-Aufgabe** („in Anlage X sperren") — workDiary steuert
 * keine Anlage, also ist der Erledigungsnachweis der Aufgabe der
 * Kontrollpunkt. `blocked` setzt der Mensch erst NACH der Sperrung in der
 * Anlage; verloren und gesperrt sind getrennte Zustände, genau diese Lücke
 * soll sichtbar sein.
 */
class AccessMediumService {
    /** @param array{holder_user_id?: int|null, holder_name?: string|null, holder_company?: string|null, expected_return_at?: \Illuminate\Support\Carbon|string|null, signature_token?: string|null} $holder */
    public function issue(AccessMedium $medium, array $holder, User $actor): AccessMediumHandover {
        if ($medium->status !== AccessMediumStatus::InStock) {
            throw new RuntimeException((string) __('Nur ein Medium im Lager kann ausgegeben werden (aktuell: :status).', ['status' => $medium->status->label()]));
        }
        if (blank($holder['holder_user_id'] ?? null) && blank($holder['holder_name'] ?? null)) {
            throw new RuntimeException((string) __('Ohne Inhaber keine Ausgabe — Nutzer oder externe Person angeben.'));
        }

        $handover = $medium->handovers()->create([
            'organization_id' => $medium->organization_id,
            'direction' => AccessMediumHandover::DIRECTION_ISSUE,
            'holder_user_id' => $holder['holder_user_id'] ?? null,
            'holder_name' => $holder['holder_name'] ?? null,
            'holder_company' => $holder['holder_company'] ?? null,
            'occurred_at' => Carbon::now(),
            'expected_return_at' => $holder['expected_return_at'] ?? null,
            'signature_token' => $holder['signature_token'] ?? null,
            'performed_by' => $actor->id,
        ]);

        $medium->forceFill([
            'status' => AccessMediumStatus::Issued,
            'holder_user_id' => $holder['holder_user_id'] ?? null,
            'holder_name' => $holder['holder_name'] ?? null,
            'holder_company' => $holder['holder_company'] ?? null,
        ])->save();
        $medium->audit('access_medium.issued', ['holder' => $medium->holderDisplay()]);

        return $handover;
    }

    public function takeBack(AccessMedium $medium, User $actor, ?string $condition = null): AccessMediumHandover {
        if ($medium->status !== AccessMediumStatus::Issued) {
            throw new RuntimeException((string) __('Nur ein ausgegebenes Medium kann zurückgenommen werden.'));
        }

        $handover = $medium->handovers()->create([
            'organization_id' => $medium->organization_id,
            'direction' => AccessMediumHandover::DIRECTION_RETURN,
            'holder_user_id' => $medium->holder_user_id,
            'holder_name' => $medium->holder_name,
            'holder_company' => $medium->holder_company,
            'occurred_at' => Carbon::now(),
            'condition' => $condition,
            'performed_by' => $actor->id,
        ]);

        $medium->forceFill([
            'status' => AccessMediumStatus::InStock,
            'holder_user_id' => null,
            'holder_name' => null,
            'holder_company' => null,
        ])->save();
        $medium->audit('access_medium.returned', ['condition' => $condition]);

        return $handover;
    }

    /**
     * Verlustmeldung: Status `lost` + verpflichtende Sperr-Aufgabe.
     *
     * Die Aufgabe trägt eine Frist (2 Tage) — überfällige Sperr-Aufgaben
     * fallen damit in die vorhandene Aufgaben-Überfälligkeit, ohne eigenen
     * Eskalationskanal.
     */
    public function reportLost(AccessMedium $medium, User $actor, ?string $note = null): Task {
        if (in_array($medium->status, [AccessMediumStatus::Lost, AccessMediumStatus::Blocked, AccessMediumStatus::Retired], true)) {
            throw new RuntimeException((string) __('Dieses Medium ist bereits verloren gemeldet, gesperrt oder ausgemustert.'));
        }

        $task = Task::query()->create([
            'organization_id' => $medium->organization_id,
            'title' => (string) __('Zutrittsmedium …:suffix in :system sperren', [
                'suffix' => $medium->number_suffix,
                'system' => $medium->system_name ?: (string) __('der Zutrittsanlage'),
            ]),
            'description' => trim(((string) __('Verlustmeldung durch :name.', ['name' => $actor->name])) . ' ' . (string) $note),
            'assigned_to' => $actor->id,
            'created_by' => $actor->id,
            'due_date' => Carbon::now()->addDays(2)->toDateString(),
        ]);

        $medium->forceFill([
            'status' => AccessMediumStatus::Lost,
            'block_task_id' => $task->id,
        ])->save();
        $medium->audit('access_medium.lost', ['task_id' => $task->id]);

        return $task;
    }

    /**
     * Sperr-Nachweis: erst wenn die Anlage gesperrt wurde, wird das Medium
     * `blocked` — und die Sperr-Aufgabe gilt als erledigt.
     */
    public function confirmBlocked(AccessMedium $medium, User $actor): AccessMedium {
        if ($medium->status !== AccessMediumStatus::Lost) {
            throw new RuntimeException((string) __('Nur ein verloren gemeldetes Medium kann als gesperrt bestätigt werden.'));
        }

        $medium->forceFill([
            'status' => AccessMediumStatus::Blocked,
            'blocked_at' => Carbon::now(),
        ])->save();

        // Erledigungsnachweis an der Aufgabe - der Kontrollpunkt der Stufe 1.
        $medium->blockTask?->forceFill(['status' => \App\Enums\Task\TaskStatus::Done->value])->save();
        $medium->audit('access_medium.blocked', ['task_id' => $medium->block_task_id]);

        return $medium;
    }

    public function retire(AccessMedium $medium, User $actor): AccessMedium {
        if ($medium->status === AccessMediumStatus::Issued) {
            throw new RuntimeException((string) __('Ein ausgegebenes Medium wird erst zurückgenommen, dann ausgemustert.'));
        }

        $medium->forceFill(['status' => AccessMediumStatus::Retired])->save();
        $medium->audit('access_medium.retired', []);

        return $medium;
    }

    /**
     * Offene Medien eines Nutzers — der Offboarding-Check: Wer geht, gibt
     * erst ab.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccessMedium>
     */
    public function openMediaFor(User $user): \Illuminate\Database\Eloquent\Collection {
        return AccessMedium::query()
            ->where('holder_user_id', $user->id)
            ->where('status', AccessMediumStatus::Issued)
            ->get();
    }
}
