<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Gitlab\Http\Controllers\GitlabWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 060): nur der Webhook-Empfang — die
 * Konfiguration läuft über die Auto-Form der Plugin-Karte.
 */

// Webhook (MVP-129): sessionlos ohne CSRF ('api'-Gruppe) — Autorisierung über
// den statischen X-Gitlab-Token-Header im Controller, Org-Zuordnung über die
// plugin_settings-ID im Pfad.
Route::middleware(['api', 'throttle:30,1'])
    ->post('api/webhooks/gitlab/{setting}', GitlabWebhookController::class)
    ->name('api.webhooks.gitlab');
