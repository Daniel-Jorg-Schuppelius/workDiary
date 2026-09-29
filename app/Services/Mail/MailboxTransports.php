<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxTransports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\Mail\Contracts\MailboxTransport;

final class MailboxTransports {
    /** @var array<string, MailboxTransport> */
    private array $transports = [];

    public function register(MailboxTransport $transport): void {
        $this->transports[$transport->key()] = $transport;
    }

    public function get(string $key): ?MailboxTransport {
        return $this->transports[$key] ?? null;
    }

    /** @return list<MailboxTransport> */
    public function all(): array {
        return array_values($this->transports);
    }
}
