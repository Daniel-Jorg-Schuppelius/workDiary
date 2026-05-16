<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AsAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Actions\Concerns;

/**
 * Marker für ausführbare Single-Action-Klassen.
 *
 * Jede Action implementiert `handle(...)` mit einer dedizierten Signatur.
 * Web- und API-Controller delegieren ihre Geschäftslogik an Actions, sodass
 * Logik testbar (Unit) und doppelfrei (Web + API teilen sich die Action) bleibt.
 *
 * Beispiel:
 *
 *  final class CreateDiaryEntry
 *  {
 *      public function handle(User $user, CreateDiaryEntryData $data): DiaryEntry { ... }
 *  }
 */
interface AsAction {
}
