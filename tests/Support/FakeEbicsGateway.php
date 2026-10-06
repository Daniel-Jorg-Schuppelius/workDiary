<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeEbicsGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Finance\PaymentRunKind;
use App\Models\Finance\EbicsConnection;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Carbon\CarbonImmutable;

/** EBICS ohne Bank (MVP-124): merkt sich Aufrufe, liefert vorgegebene Auszüge. */
final class FakeEbicsGateway implements EbicsGateway {
    /** @var list<string> */
    public array $calls = [];

    /** @var list<string> */
    public array $statements = [];

    /** @var list<array{kind: PaymentRunKind, xml: string, file: string}> */
    public array $uploads = [];

    public ?string $failWith = null;

    /** Fehler ohne Antwort der Bank (z. B. Zeitüberschreitung) — der Ausgang bleibt offen. */
    public ?\Throwable $throws = null;

    public function createKeys(EbicsConnection $connection): void {
        $this->calls[] = 'keys';
        $connection->forceFill(['keyring' => '{"fake":true}', 'keyring_secret' => 'secret-passphrase'])->save();
    }

    public function sendInitialization(EbicsConnection $connection): void {
        $this->guard('initialize');
    }

    public function letterKeys(EbicsConnection $connection): array {
        return [
            ['type' => 'A', 'version' => 'A006', 'hash' => str_repeat('ab', 32), 'certificate_created_at' => '2026-10-03'],
            ['type' => 'X', 'version' => 'X002', 'hash' => str_repeat('cd', 32), 'certificate_created_at' => '2026-10-03'],
            ['type' => 'E', 'version' => 'E002', 'hash' => str_repeat('ef', 32), 'certificate_created_at' => '2026-10-03'],
        ];
    }

    public function fetchBankKeys(EbicsConnection $connection): void {
        $this->guard('activate');
    }

    public function downloadStatements(EbicsConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array {
        $this->guard('download:' . $from->toDateString() . '..' . $to->toDateString());

        return $this->statements;
    }

    public function uploadPayment(EbicsConnection $connection, PaymentRunKind $kind, string $xml, string $fileName): string {
        $this->guard('upload');
        $this->uploads[] = ['kind' => $kind, 'xml' => $xml, 'file' => $fileName];

        return 'A' . str_pad((string) count($this->uploads), 3, '0', STR_PAD_LEFT);
    }

    public function suspend(EbicsConnection $connection): void {
        $this->guard('suspend');
    }

    private function guard(string $call): void {
        $this->calls[] = $call;
        if ($this->throws !== null) {
            throw $this->throws;
        }
        if ($this->failWith !== null) {
            throw new EbicsException('bank_rejected', $this->failWith, 'Testfehler');
        }
    }
}
