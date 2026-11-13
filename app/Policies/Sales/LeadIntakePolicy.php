<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Enums\User\Permission;
use App\Models\Attachments\Attachment;
use App\Models\Platform\User;
use App\Models\Sales\{Lead, LeadIntake};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\Sales\Intake\{IntakeKeys, LeadIntakeVisibility};

/**
 * Aufnahmeakte am Lead (Feature 200). Sehen und Ausfüllen wie der Lead
 * (Kundenrechte) — den Bogen füllt aus, wer den Lead bearbeiten darf;
 * entscheiden nur mit `lead.intake.decide`. Anhänge folgen der Akte, eng
 * sichtbare Unterlagen (Ausweiskopie, Gesundheitsunterlagen) zusätzlich nur
 * mit ihrem eigenen Recht ({@see viewAttachment()}).
 */
class LeadIntakePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(Permission::CustomerViewAny->value);
    }

    public function view(User $user, LeadIntake $intake): bool {
        return $this->sharesOrganization($user, $intake) && $user->can(Permission::CustomerView->value);
    }

    /** Akte am Lead öffnen. */
    public function create(User $user, ?Lead $lead = null): bool {
        return $user->can(Permission::CustomerUpdate->value)
            && ($lead === null || ($this->sharesOrganization($user, $lead) && $lead->anonymized_at === null));
    }

    /** Stufe wechseln, Unterlagen ablegen — bis zur Entscheidung. */
    public function update(User $user, LeadIntake $intake): bool {
        return $this->sharesOrganization($user, $intake)
            && $user->can(Permission::CustomerUpdate->value)
            && $intake->acceptsFieldAutosave();
    }

    /** Speichern je Feld (Vertrag AutosavesFieldDocument, Feature 203). */
    public function autosave(User $user, LeadIntake $intake): bool {
        return $this->update($user, $intake);
    }

    public function decide(User $user, LeadIntake $intake): bool {
        return $this->sharesOrganization($user, $intake) && $user->can(Permission::LeadIntakeDecide->value);
    }

    /** Neue Fassung nach der Annahme (Aktualisierung). */
    public function refresh(User $user, LeadIntake $intake): bool {
        return $this->sharesOrganization($user, $intake)
            && $user->can(Permission::CustomerUpdate->value)
            && $intake->isLatest()
            && $intake->isAccepted();
    }

    /** PDF ohne enge Angaben als Dokument am Kunden bzw. Lead ablegen. */
    public function archive(User $user, LeadIntake $intake): bool {
        return $this->view($user, $intake) && $user->can(Permission::CustomerUpdate->value);
    }

    /** Prüfpakete einschalten, Aktualisierung je Risikostufe, zweiter Blick. */
    public function manageSettings(User $user): bool {
        return $user->can(Permission::LeadIntakeDecide->value);
    }

    /** Ende der Geschäftsbeziehung am Kunden setzen (Anker der Aufbewahrung). */
    public function endRelationship(User $user): bool {
        return $user->can(Permission::CustomerUpdate->value);
    }

    /** Anhang der Akte: Sicht auf die Akte und — bei eng sichtbaren Unterlagen — deren Recht. */
    public function viewAttachment(User $user, LeadIntake $intake, Attachment $attachment): bool {
        if (! $this->view($user, $intake)) {
            return false;
        }
        $meta = (string) $attachment->meta_type;
        if (! str_starts_with($meta, IntakeKeys::ATTACHMENT_META)) {
            return true;
        }

        return app(LeadIntakeVisibility::class)->canSeeKey($intake, $user, substr($meta, strlen(IntakeKeys::ATTACHMENT_META)));
    }
}
