<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Chat“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class ChatManifest extends Manifest {
    public function code(): string {
        return 'chat';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'Chat';
    }

    public function licenseCode(): string {
        return 'module.chat';
    }

    public function description(): string {
        return 'Interner Team-Chat.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Chat',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'chat_channel_user',
            'chat_channels',
            'chat_message_reactions',
            'chat_message_stars',
            'chat_messages',
            'chat_poll_options',
            'chat_poll_votes',
            'chat_polls',
            'chat_reminders',
            'chat_scheduled_messages',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'chat.*',
        ];
    }
}
