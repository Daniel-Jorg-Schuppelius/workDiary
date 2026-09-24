<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorPdfRenderer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * PDF eines Belegs für die Dokumentspiegelung (Welle 4.2): das Fachmodul
 * liefert exakt den Download-Renderer. Registrierung über `Manifest::extensions()`.
 */
interface MirrorPdfRenderer {
    /** @return class-string<Model> */
    public function modelClass(): string;

    public function pdf(Model $document): string;
}
