<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Attachments\Search;

use App\Enums\User\Permission;
use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\{CarbonFmt, MorphMap};
use Illuminate\Support\Facades\{Gate, URL};

/**
 * Anhang-Metadaten (Vollaudit M8) nach Dateiname, bei Mail-Dateien auch nach
 * Betreff, Absender und Text (Feature 204, `attachment_texts`): Anhänge
 * sichtbarer Aufträge und eigene Uploads als Vorauswahl, jeder Treffer
 * zusätzlich durch die AttachmentPolicy (Policy des Trägers). Der Download
 * prüft die Signatur und die Policy, die Ansicht „Mail“ die Policy.
 */
final class AttachmentSearchDomain implements SearchDomain {
    public function key(): string {
        return 'attachments';
    }

    public function label(): string {
        return (string) __('Anhänge');
    }

    public function icon(): string {
        return 'attach_file';
    }

    public function order(): int {
        return 90;
    }

    public function available(User $user): bool {
        return true;
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->customer !== null) {
            return [];
        }
        $query = Attachment::query()
            ->where('organization_id', $organizationId)
            // Dateiname, bei Mail-Dateien auch Betreff, Absender und Text (Feature 204).
            ->where(function ($q) use ($term): void {
                $q->whereLikeEscaped('original_name', $term)
                    ->orWhereExists(function ($sub) use ($term): void {
                        $sub->selectRaw('1')
                            ->from('attachment_texts')
                            ->whereColumn('attachment_texts.attachment_id', 'attachments.id')
                            ->whereLikeEscaped('attachment_texts.text', $term);
                    });
            })
            ->whereNull('meta_type')
            ->when($filters->person !== null, fn ($q) => $q->where('user_id', $filters->person))
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id)
                    ->orWhere(function ($p) use ($user): void {
                        $p->where('attachable_type', MorphMap::alias(DiaryEntry::class))
                            ->whereExists(function ($sub) use ($user): void {
                                $sub->selectRaw('1')
                                    ->from('diary_entries')
                                    ->whereColumn('diary_entries.id', 'attachments.attachable_id');
                                if (! ($user->isAdmin() || $user->can(Permission::DiaryViewAny->value))) {
                                    $sub->where(fn ($w) => $w->where('diary_entries.user_id', $user->id)
                                        ->orWhere('diary_entries.assigned_user_id', $user->id));
                                }
                            });
                    });
            });
        $filters->range($query, 'created_at');

        // Die Abfrage grenzt grob ein; ob ein Treffer erscheint, entscheidet die AttachmentPolicy
        // (Organisation + Policy des Trägers, Feature 204/N3) — wie beim Öffnen.
        return array_values($query->with('attachable')->orderByDesc('created_at')->limit(min(3 * $limit, 150))->get()
            ->filter(fn (Attachment $a): bool => Gate::forUser($user)->allows('view', $a))
            ->take($limit)
            ->map(fn (Attachment $a) => [
                'id' => $a->id,
                'title' => (string) $a->original_name,
                'subtitle' => ($a->created_at !== null ? CarbonFmt::fdate(CarbonFmt::orgTz($a->created_at)) : '')
                    . ($a->mime ? ' · ' . $a->mime : ''),
                // Mail-Datei → Ansicht „Mail“ (prüft die Policy); sonst der Download, der die Signatur prüft —
                // ein nackter Link endet dort im 403.
                'url' => $a->mailFormat() !== null ? route('attachments.mail.show', $a) : URL::signedRoute('attachments.download', $a),
            ])
            ->all());
    }
}
