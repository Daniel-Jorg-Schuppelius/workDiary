<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Github\Http\Controllers\GithubWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 060): nur der Webhook-Empfang — die
 * Konfiguration läuft über die Auto-Form der Plugin-Karte.
 */

// Webhook (MVP-129): sessionlos ohne CSRF ('api'-Gruppe) — Autorisierung über
// die HMAC-Signatur (X-Hub-Signature-256) des Raw-Bodys im Controller,
// Org-Zuordnung über die plugin_settings-ID im Pfad.
Route::middleware(['api', 'throttle:30,1'])
    ->post('api/webhooks/github/{setting}', GithubWebhookController::class)
    ->name('api.webhooks.github');
