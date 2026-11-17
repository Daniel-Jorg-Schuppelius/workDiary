<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Diary;

use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\Delegation\ActsForResolver;

/**
 * Vertretung (Feature 202, MVP-1648): Was die Zuweisung erlaubt (Lesen mit
 * `diary.viewOwn`, Lebenszyklus-Schritte), darf auch, wer die zugewiesene
 * Person gerade vertritt ({@see isAssignee()}). Rechte prüfen weiter die
 * handelnde Person — eine Vertretung ohne ein Recht bekommt es nicht.
 */
class DiaryEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, DiaryEntry $entry): bool {
        // Eigene und zugewiesene mit diary.viewOwn; Ändern und Löschen bleiben
        // Eigentum oder diary.viewAny plus Recht.
        return $this->sharesOrganization($user, $entry)
            && (
                (
                    ($this->owns($user, $entry) || $this->isAssignee($user, $entry) || $this->isNamedReviewer($user, $entry))
                    && $user->can(Permission::DiaryViewOwn->value)
                )
                || $user->can(Permission::DiaryViewAny->value)
            );
    }

    public function update(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryUpdate->value)
                )
            );
    }

    public function delete(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryDelete->value)
                )
            );
    }

    public function archive(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can(Permission::DiaryUpdate->value)
                )
            );
    }

    public function accept(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderAccept);
    }

    /** Annahme zurückgeben: dasselbe Recht wie das Annehmen. */
    public function reject(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderAccept);
    }

    public function start(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function pause(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function resume(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function complete(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderComplete);
    }

    public function handover(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderHandover);
    }

    public function markInvoiced(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && $user->can(Permission::OrderMarkInvoiced->value);
    }

    public function cancel(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderCancel);
    }

    /** Zur Prüfung vorlegen, zurückziehen, erneut vorlegen (Feature 198): Arbeit der Bearbeitung. */
    public function submitReview(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function withdrawReview(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    public function resubmitReview(User $user, DiaryEntry $entry): bool {
        return $this->canAct($user, $entry, Permission::OrderWork);
    }

    /**
     * Prüfen (freigeben, Rückfrage): wer `order.review` hat oder als prüfende
     * Person benannt ist — nie die Person, die vorgelegt hat (Feature 198).
     */
    public function approveReview(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && ($user->can(Permission::OrderReview->value) || $this->isNamedReviewer($user, $entry))
            && (int) $entry->review_submitter_user_id !== (int) $user->id;
    }

    public function queryReview(User $user, DiaryEntry $entry): bool {
        return $this->approveReview($user, $entry);
    }

    /** Freigeben und abschließen: Prüfrecht und Abschlussrecht. */
    public function approveAndComplete(User $user, DiaryEntry $entry): bool {
        return $this->approveReview($user, $entry) && $this->canAct($user, $entry, Permission::OrderComplete);
    }

    /** Prüfpflicht am einzelnen Auftrag ab- oder einschalten (eigenes Recht, auditiert). */
    public function waiveReview(User $user, DiaryEntry $entry): bool {
        return $this->sharesOrganization($user, $entry)
            && $user->can(Permission::OrderWaiveReview->value);
    }

    /** Folgeperiode anlegen (Feature 198, MVP-1637): Auftrag sehen und Aufträge anlegen dürfen. */
    public function createFollowUp(User $user, DiaryEntry $entry): bool {
        return $this->view($user, $entry)
            && $user->can(Permission::DiaryCreate->value);
    }

    /** Als prüfende Person benannt (Feature 198). */
    private function isNamedReviewer(User $user, DiaryEntry $entry): bool {
        return $entry->reviewer_user_id !== null && (int) $entry->reviewer_user_id === (int) $user->id;
    }

    private function canAct(User $user, DiaryEntry $entry, Permission $permission): bool {
        return $this->sharesOrganization($user, $entry)
            && (
                $this->owns($user, $entry)
                || $this->isAssignee($user, $entry)
                || (
                    $user->can(Permission::DiaryViewAny->value)
                    && $user->can($permission->value)
                )
            );
    }

    /** Zugewiesen an die Person selbst oder an jemanden, den sie heute vertritt (Feature 202). */
    private function isAssignee(User $user, DiaryEntry $entry): bool {
        if ($entry->assigned_user_id === null) {
            return false;
        }

        return (int) $entry->assigned_user_id === (int) $user->id
            || app(ActsForResolver::class)->actsFor($user, (int) $entry->assigned_user_id);
    }
}
