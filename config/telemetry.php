<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : telemetry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Telemetrie (Feature 036, MVP-337): Schalter für die LOKALEN
 * Feature-Nutzungszähler (feature_usage_counters, Tages-Aggregat je
 * Organisation + Feature — ohne Personenbezug, ohne fachliche Inhalte).
 *
 * Es verlassen KEINE Daten die Installation; darum ist der Default
 * bewusst AN (Opt-out) — anders als updates.check_mode, dessen
 * Opt-in-Semantik externe Kommunikation gated. Sollte je ein externer
 * Versand hinzukommen, braucht er einen EIGENEN Opt-in-Schalter mit
 * Default aus.
 *
 * Override-Ebenen (Setting::get-Auflösung): Org-Override
 * (organizations.settings) → System-Override (system_settings) →
 * dieser config-Default (ENV TELEMETRY_ENABLED).
 */

return [
    'enabled' => env('TELEMETRY_ENABLED', true),
];
