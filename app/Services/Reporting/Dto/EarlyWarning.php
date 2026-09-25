<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EarlyWarning.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reporting\Dto;

use Illuminate\Database\Eloquent\Model;

/**
 * Eine Frühwarnung (MVP-889). `recommendation` ist ein Übersetzungsschlüssel
 * mit einer festen Empfehlung; `notify` = false, wenn die Quelle schon einen
 * eigenen Hinweisweg hat (z. B. Reklamationsmuster).
 */
final readonly class EarlyWarning {
    /** @param array<string, scalar> $params Platzhalter für Titel und Detail */
    public function __construct(
        public string $kind,
        public Model $subject,
        public string $title,
        public string $detail,
        public string $recommendation,
        public ?string $url = null,
        public bool $notify = true,
        public array $params = [],
    ) {}
}
