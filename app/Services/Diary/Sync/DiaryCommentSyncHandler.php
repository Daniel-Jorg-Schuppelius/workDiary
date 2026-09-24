<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryCommentSyncHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Sync;

use App\Models\Communication\Comment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Sync\Contracts\SyncCommandHandler;
use App\Support\{Setting, Sqid};
use Illuminate\Support\Facades\{Gate, Validator};
use RuntimeException;

/** Offline-Kommentar am Auftrag (Feature 035). */
final class DiaryCommentSyncHandler implements SyncCommandHandler {
    /** @return list<string> */
    public function types(): array {
        return ['comment.diary'];
    }

    public function handle(User $user, string $type, array $payload): string {
        return match ($type) {
            'comment.diary' => $this->commentDiary($user, $payload),
            default => throw new RuntimeException('Unbekannter Sync-Befehlstyp: ' . $type),
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function commentDiary(User $user, array $payload): string {
        if (! Gate::forUser($user)->allows('create', Comment::class)) {
            throw new RuntimeException((string) __('Keine Berechtigung für Kommentare.'));
        }

        $data = Validator::make($payload, [
            'diary' => ['required', 'string'],
            'body' => ['required', 'string', 'max:' . (int) Setting::get('validation.comment.body_max', 5000)],
        ])->validate();

        // Sqid immer gegen die Zielmodellklasse dekodieren; der
        // OrganizationScope zieht die Mandantengrenze der Auflösung.
        $diary = DiaryEntry::query()
            ->whereKey(Sqid::decode(DiaryEntry::class, $data['diary']))
            ->first();

        if ($diary === null) {
            throw new RuntimeException((string) __('Auftrag nicht gefunden.'));
        }

        $comment = $diary->comments()->create([
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);

        return 'comments:' . $comment->id;
    }
}
