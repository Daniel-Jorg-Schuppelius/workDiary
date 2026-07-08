<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatMessageFormatter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\ChatWebhook;

/**
 * Formatiert eine Benachrichtigung ({title, message?, url?}) in das jeweilige
 * Chat-Payload (Feature 056, MVP-119): Microsoft Teams erwartet eine Adaptive
 * Card im Workflows-Umschlag (`type: message` + `attachments[]`, da die alten
 * O365-Connectors/MessageCards von Microsoft abgekündigt sind),
 * Mattermost/Rocket.Chat ein schlichtes `{text}` (Markdown).
 */
class ChatMessageFormatter {
    /**
     * @param  array{title: string, message?: string|null, url?: string|null}  $payload
     * @return array<string, mixed>
     */
    public function format(string $kind, string $eventLabel, array $payload): array {
        return $kind === ChatWebhook::KIND_TEAMS
            ? $this->teams($eventLabel, $payload)
            : $this->mattermost($eventLabel, $payload);
    }

    /**
     * @param  array{title: string, message?: string|null, url?: string|null}  $payload
     * @return array<string, mixed>
     */
    private function teams(string $eventLabel, array $payload): array {
        $body = [
            ['type' => 'TextBlock', 'text' => $payload['title'], 'weight' => 'Bolder', 'size' => 'Medium', 'wrap' => true],
            ['type' => 'TextBlock', 'text' => $eventLabel, 'isSubtle' => true, 'spacing' => 'None', 'wrap' => true],
        ];

        $message = (string) ($payload['message'] ?? '');
        if ($message !== '') {
            $body[] = ['type' => 'TextBlock', 'text' => $message, 'wrap' => true];
        }

        $card = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => $body,
        ];

        $url = (string) ($payload['url'] ?? '');
        if ($url !== '') {
            $card['actions'] = [[
                'type' => 'Action.OpenUrl',
                'title' => (string) __('chat.open'),
                'url' => $url,
            ]];
        }

        // Teams-Workflows-Umschlag: eine Adaptive Card als Attachment.
        return [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'content' => $card,
            ]],
        ];
    }

    /**
     * @param  array{title: string, message?: string|null, url?: string|null}  $payload
     * @return array<string, mixed>
     */
    private function mattermost(string $eventLabel, array $payload): array {
        $text = '**' . $payload['title'] . '**';
        $message = (string) ($payload['message'] ?? '');
        if ($message !== '') {
            $text .= "\n" . $message;
        }
        $url = (string) ($payload['url'] ?? '');
        if ($url !== '') {
            $text .= "\n" . $url;
        }

        return ['text' => $text];
    }
}
