<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderItem.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support\Reminders;

/**
 * Schlanker Wert-Container für einen einzelnen Reminder-Hinweis im UI/Digest.
 *
 * Die Reminder werden zur Laufzeit aus den Domänen-Daten berechnet und nicht
 * persistiert. `severity` steuert die Farbgebung im Header-Dropdown.
 */
final class ReminderItem {
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly string $url,
        public readonly string $icon = 'notifications',
        public readonly string $severity = 'info',
        public readonly int $count = 1,
    ) {}

    /**
     * @return array{key:string,title:string,description:string,url:string,icon:string,severity:string,count:int}
     */
    public function toArray(): array {
        return [
            'key' => $this->key,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'icon' => $this->icon,
            'severity' => $this->severity,
            'count' => $this->count,
        ];
    }
}
