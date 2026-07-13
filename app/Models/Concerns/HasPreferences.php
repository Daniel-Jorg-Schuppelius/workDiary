<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasPreferences.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

/**
 * Per-User-Präferenzen (preferences-Bag inkl. Defaults aus
 * config/personalization.php): Lesen/Schreiben, Arbeitsmodus, Locale- und
 * Zeitzonen-Präferenz. Aus dem User-Modell extrahiert
 * (Refactoring Welle 2, B6b) — Verhalten unverändert.
 *
 * @property array<string, mixed>|null $preferences
 */
trait HasPreferences {
    /**
     * Persistierter Arbeitsmodus des Users, normalisiert auf einen tatsächlich
     * erlaubten Wert. Dient als Default, wenn die Session (noch) keinen
     * work_mode trägt – so überlebt die Modus-Wahl Session-Ablauf, neuen Login
     * und F5. Liegt in der Per-User-Präferenz-Bag (preferences['work_mode']).
     */
    public function preferredWorkMode(): string {
        $stored = $this->getPreference('work_mode');
        $mode = in_array($stored, ['legacy', 'new'], true) ? $stored : 'legacy';

        if ($mode === 'new' && ! $this->canAccessNew()) {
            return $this->canAccessLegacy() ? 'legacy' : 'new';
        }
        if ($mode === 'legacy' && ! $this->canAccessLegacy()) {
            return $this->canAccessNew() ? 'new' : 'legacy';
        }

        return $mode;
    }

    /**
     * Liest eine Per-User-Präferenz aus preferences (inkl. Merge mit den
     * Defaults aus config/personalization.php).
     */
    public function getPreference(string $key, mixed $default = null): mixed {
        return $this->preferences()[$key] ?? $default;
    }

    /**
     * Bevorzugte Sprache für Benachrichtigungen/Mails (HasLocalePreference):
     * Laravel rendert Notifications damit je Empfänger — wichtig für
     * Scheduler-/Queue-Versand, der sonst in der App-Default-Locale liefe.
     * Kaskade wie Locales::current(), nur ohne Session (User ≠ Betrachter).
     */
    public function preferredLocale(): ?string {
        $locale = $this->getPreference('locale');
        if (is_string($locale) && \App\Support\Locales::isSupported($locale)) {
            return $locale;
        }

        $orgLocale = $this->organization?->locale;

        return is_string($orgLocale) && \App\Support\Locales::isSupported($orgLocale) ? $orgLocale : null;
    }

    /**
     * Setzt eine Per-User-Präferenz in der preferences-Bag und persistiert sie.
     * Zentraler Schreibweg, damit Caller nicht selbst mergen müssen.
     */
    public function setPreference(string $key, mixed $value): void {
        $stored = (array) ($this->getAttribute('preferences') ?? []);
        $stored[$key] = $value;
        $this->setAttribute('preferences', $stored);
        $this->save();
    }

    /**
     * Persönliche Präferenzen gemerged mit den Defaults aus
     * config/personalization.php. Liefert immer ein vollständig
     * gefülltes Array; leere Felder bedeuten "Default verwenden".
     *
     * @return array<string, mixed>
     */
    public function preferences(): array {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('personalization.defaults', []);
        /** @var array<string, mixed> $stored */
        $stored = (array) ($this->preferences ?? []);

        return array_replace($defaults, $stored);
    }

    /**
     * Persönliche Anzeige-Zeitzone (Override der Organisations-Zeitzone).
     * Liegt in preferences['timezone']; null bedeutet "Organisation verwenden".
     * Wird von App\Support\Tz ausgewertet.
     */
    public function getTimezoneAttribute(): ?string {
        $stored = (array) ($this->preferences ?? []);
        $tz = $stored['timezone'] ?? null;

        return is_string($tz) && $tz !== '' ? $tz : null;
    }
}
