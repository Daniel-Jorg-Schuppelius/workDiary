<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardLayoutItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetWidth;

/**
 * Eine Kachel mit dem für den Nutzer aufgelösten Layout (Nutzerwahl →
 * Organisationsvorgabe → Vorgabe der Widget-Klasse).
 */
final readonly class DashboardLayoutItem {
    public function __construct(
        public Widget $widget,
        public int $sortOrder,
        public bool $hidden,
        public WidgetWidth $width,
        /** Bereich (Tab), in dem die Kachel steht; null = erster Bereich. */
        public ?string $tabKey,
        /** Woher die Sichtbarkeit/Position stammt: 'user', 'organization' oder 'default'. */
        public string $source,
    ) {}

    public function key(): string {
        return $this->widget->key();
    }
}
