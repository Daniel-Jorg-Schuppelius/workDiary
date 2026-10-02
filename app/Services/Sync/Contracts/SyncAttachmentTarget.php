<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncAttachmentTarget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sync\Contracts;

use App\Models\Platform\User;
use Illuminate\Http\UploadedFile;

/**
 * Ein {@see SyncCommandHandler}, der offline angekündigte Dateien annimmt
 * (MVP-1059). Der Anhangsweg des Offline-Syncs findet den Handler über den Typ
 * des bereits angewendeten Befehls; `$resultRef` ist dessen Ergebnis
 * (`<tabelle>:<id>`). `false` heißt: Ziel gibt es nicht mehr (HTTP 410).
 */
interface SyncAttachmentTarget {
    public function attach(User $user, string $type, string $resultRef, string $field, UploadedFile $file): bool;
}
