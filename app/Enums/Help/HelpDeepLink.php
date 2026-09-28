<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpDeepLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Help;

use Illuminate\Support\Facades\Route;

/**
 * Hilfe-Abschnitte, auf die Fehlerseiten verweisen (MVP-972). Der Wert ist der
 * feste Anker (`## Titel {#wert}`) im Thema {@see self::TOPIC} — in allen
 * Sprachen gleich; ein Test prüft jede Sprache.
 */
enum HelpDeepLink: string {
    case Forbidden = 'forbidden';
    case NotFound = 'not-found';
    case SessionExpired = 'session-expired';
    case PlanLocked = 'plan-locked';
    case ServerError = 'server-error';
    case AreaMaintenance = 'area-maintenance';

    public const TOPIC = 'help.errors';

    public function anchor(): string {
        return 'sec-' . $this->value;
    }

    /** Null, wenn das Hilfecenter nicht erreichbar ist (ohne Anmeldung, ohne Route). */
    public function url(): ?string {
        if (! auth()->check() || ! Route::has('help.center.show')) {
            return null;
        }

        return route('help.center.show', ['topic' => self::TOPIC]) . '#' . $this->anchor();
    }
}
