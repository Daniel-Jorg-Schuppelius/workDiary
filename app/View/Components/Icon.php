<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Icon.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;

/**
 * Material-Symbol (`<x-icon name="edit" />`). Jede Seite setzt über 200 davon; als Blade-View kostete
 * jedes rund 0,1 ms in der View-Fabrik. Die Klasse schreibt das Markup selbst — dieselbe Ausgabe,
 * Zeichen für Zeichen. Sie gibt sich selbst als `Htmlable` zurück, weil die Attribute erst nach
 * `render()` am Baustein ankommen.
 */
final class Icon extends Component implements Htmlable {
    public function __construct(
        public ?string $name = null,
        public mixed $filled = false,
        public mixed $weight = 400,
        public mixed $size = null,
    ) {}

    public function render(): static {
        return $this;
    }

    public function toHtml(): string {
        $name = e($this->name);

        return "<span\n    " . $this->attributes->class(['material-symbols-outlined leading-none align-middle shrink-0 select-none']) . "\n"
            . "    style=\"font-variation-settings: 'FILL' " . ($this->filled ? 1 : 0) . ", 'wght' " . (int) $this->weight . ';' . e($this->size ? ' font-size: ' . $this->size . ';' : '') . "\"\n"
            . "    aria-hidden=\"true\"\n"
            . '    data-icon="' . $name . "\"\n"
            . '>' . $name . "</span>\n";
    }
}
