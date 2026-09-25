<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RuleTrigger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Automation\Triggers;

/**
 * Erweiterungspunkt: ein Ereignis, auf das Automationsregeln reagieren
 * können. Das Modul, das `RuleEngine::dispatch()` mit dem Schlüssel aufruft,
 * nennt die Klasse in `Manifest::extensions()`.
 */
interface RuleTrigger {
    /** Schlüssel in `automation_rules.trigger_event`. */
    public function key(): string;

    public function label(): string;

    /**
     * Vorbelegung des Bedingungsfelds im Regelformular.
     *
     * @return array<string, mixed>
     */
    public function exampleConditions(): array;
}
