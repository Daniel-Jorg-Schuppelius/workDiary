<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Attachments;

use App\Models\Attachments\Attachment;
use App\Models\Platform\{Organization, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    /**
     * Defense in Depth zusätzlich zum OrganizationScope: verweigert
     * den Zugriff, sobald das Attachment einer anderen Organisation
     * als der aktuell aktiven gehört. Greift auch in Konsolen-/Queue-
     * Kontexten, in denen der Global Scope nicht aktiv ist.
     */
    public function view(User $user, Attachment $attachment): bool {
        if (! $this->sharesOrganization($user, $attachment)) {
            return false;
        }

        // Chat-Anhänge zusätzlich auf Kanal-Mitgliedschaft einschränken
        // (private Kanäle): gleiche Organisation allein genügt hier nicht.
        $parent = $attachment->attachable;
        if ($parent instanceof \App\Models\Chat\Message) {
            $channel = $parent->channel;

            return $channel !== null && (
                $channel->hasMember($user)
                || ($channel->type === 'channel' && $channel->visibility === 'public')
            );
        }

        // Das Traegerobjekt entscheidet mit, wenn es eine eigene Regel hat
        // (Sicherheitsaudit 2026-09-13): Vorher genuegte die gemeinsame
        // Organisation — wer eine Anhang-Kennung kannte, lud auch die Datei
        // eines Vorgangs herunter, den er selbst nicht oeffnen darf. Nur wo es
        // gar keine Regel gibt, bleibt es bei der Mandantengrenze.
        if ($parent !== null && Gate::getPolicyFor($parent) !== null) {
            return Gate::forUser($user)->allows('view', $parent);
        }

        // Lern-Abgaben: die eigene Abgabe darf man immer sehen, fremde nur mit
        // Sicht auf den Kurs (Trainer/Verwaltung) — die Einschreibung selbst
        // hat keine Policy, der Kurs schon.
        $enrollment = match (true) {
            $parent instanceof \App\Models\Learning\LearningSubmission => $parent->enrollment,
            $parent instanceof \App\Models\Learning\LearningAnswer => $parent->attempt?->enrollment,
            default => null,
        };
        if ($enrollment !== null) {
            if ((int) $enrollment->user_id === (int) $user->id) {
                return true;
            }
            $course = $enrollment->course;

            return $course !== null && Gate::forUser($user)->allows('view', $course);
        }

        // Träger ohne eigene Regel, aber mit Elternobjekt: das Elternobjekt
        // entscheidet. Ohne diese Auflösung reichte die gemeinsame Organisation
        // — Mailanhänge interner Ticket-Notizen und fremde Lern-Abgaben waren
        // über die Anhang-Kennung abrufbar
        // (Sicherheitsaudit 2026-09-17, idor-attachment-5).
        $delegate = self::delegateFor($parent);
        if ($delegate !== null) {
            return Gate::forUser($user)->allows('view', $delegate);
        }

        return true;
    }

    /**
     * Elternobjekt eines Trägers ohne eigene Policy. Neue Träger gehören in
     * diese Liste oder brauchen eine eigene Policy — das Architektur-Gate
     * {@see \Tests\Unit\Architecture\AttachmentCarrierPolicyRuleTest} hält
     * das nach.
     */
    private static function delegateFor(mixed $parent): ?\Illuminate\Database\Eloquent\Model {
        $target = match (true) {
            $parent instanceof \App\Models\ServiceTicket\ServiceTicketMessage => $parent->ticket,
            $parent instanceof \App\Models\Learning\LearningUnit => $parent->course,
            $parent instanceof \App\Models\Protocol\ProtocolItem => $parent->protocol,
            $parent instanceof \App\Models\Disposal\DisposalItem => $parent->job,
            $parent instanceof \App\Models\AssetFinance\AssetFinanceEndProcess => $parent->contract,
            $parent instanceof \App\Models\Supplier\SupplierCredential => $parent->supplier,
            $parent instanceof \App\Models\AssetCompliance\AssetInspectionEvent => $parent->asset,
            $parent instanceof \App\Models\AssetCompliance\AssetInspectionSchedule => $parent->asset,
            $parent instanceof \App\Models\Rental\RentalHandoverReport => $parent->asset,
            $parent instanceof \App\Models\Rental\RentalReturnReport => $parent->asset,
            $parent instanceof \App\Models\Asset\AssetDefect => $parent->asset,
            default => null,
        };

        return $target instanceof \Illuminate\Database\Eloquent\Model ? $target : null;
    }

    public function create(User $user): bool {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool {
        // Kassenbelege (MVP-414) sind GoBD-append-only: der Beleg gehört zur
        // Hash-Kette des Eintrags und darf nie entfernt werden (Vollaudit
        // 2026-07, M37) — auch nicht durch den Uploader.
        if ($attachment->attachable instanceof \App\Models\Finance\CashEntry) {
            return false;
        }

        return $this->sharesOrganization($user, $attachment) && $this->owns($user, $attachment);
    }

    private function sharesOrganization(User $user, Attachment $attachment): bool {
        $attachmentOrgId = $attachment->organization_id;

        // Globale/legacy-Anhänge (kein Org-Bezug, z. B. Logo der
        // Plattform-Organisation) bleiben für jeden eingeloggten
        // Benutzer sichtbar.
        if ($attachmentOrgId === null) {
            return true;
        }

        $activeOrgId = null;
        if (app()->bound('currentOrganization')) {
            $current = app('currentOrganization');
            if ($current instanceof Organization) {
                $activeOrgId = $current->id;
            }
        }

        if ($activeOrgId !== null) {
            return (int) $attachmentOrgId === (int) $activeOrgId;
        }

        return $user->organization_id !== null
            && (int) $attachmentOrgId === (int) $user->organization_id;
    }
}
