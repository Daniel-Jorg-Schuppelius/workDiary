<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuardsAttachments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Attachments\Attachment;
use App\Models\Platform\User;

/**
 * Träger, der über seine Anhänge mitentscheidet (MVP-1573): Er sperrt Upload
 * und Löschen, sobald er ein Nachweis ist, und führt beides in seinem Journal.
 * Der Anhangs-Controller fragt ihn, weil der Admin-Durchgriff der Policy die
 * Sperre sonst überginge.
 */
interface GuardsAttachments {
    /** Ohne Anhang: Darf einer hinzukommen? Mit Anhang: Darf dieser entfallen? */
    public function attachmentsLocked(?Attachment $attachment = null): bool;

    public function attachmentAdded(Attachment $attachment, ?User $actor): void;

    public function attachmentRemoved(Attachment $attachment, ?User $actor): void;
}
