<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UiAction.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Ui;

/**
 * Symbol-Aktion, die ein Plugin oder eine Quelle der Oberfläche liefert
 * (Belegliste, Belegfluss, Verbindungen). Darstellung über `<x-ui-action>`;
 * `post` sendet ein Formular mit CSRF-Token, `confirm` fragt vorher nach.
 */
final readonly class UiAction {
    public function __construct(
        public string $icon,
        public string $label,
        public string $url,
        public bool $post = false,
        public string $tone = 'ghost',
        public bool $modal = false,
        public ?string $confirm = null,
    ) {}
}
