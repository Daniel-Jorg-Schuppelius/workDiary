<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LibraryEbicsGateway.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Ebics;

use App\Enums\Finance\PaymentRunKind;
use App\Models\Finance\EbicsConnection;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use App\Services\Finance\Ebics\Exceptions\EbicsException;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\JsonHelper;
use EbicsApi\Ebics\Contexts\{BTDContext, BTUContext};
use EbicsApi\Ebics\Contracts\{EbicsClientInterface, EbicsResponseExceptionInterface};
use EbicsApi\Ebics\{EbicsBankLetter, EbicsClient};
use EbicsApi\Ebics\Exceptions\NoDownloadDataAvailableException;
use EbicsApi\Ebics\Factories\DocumentFactory;
use EbicsApi\Ebics\Models\{Bank, Keyring, SignatureBankLetter, User, XmlDocument};
use EbicsApi\Ebics\Models\EbicsClientOptions;
use EbicsApi\Ebics\Models\X509\BankX509Generator;
use EbicsApi\Ebics\Orders\{BTD, BTU, HIA, HPB, INI, SPR};
use EbicsApi\Ebics\Services\ArrayKeyringManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * EBICS 3.0 über `ebics-api/ebics-client-php` (MVP-124). Deutsche Zuordnung
 * der Geschäftsvorfälle (DK): Tagesauszug C53 = BTD EOP/DE/camt.053 im ZIP,
 * Überweisung CCT = BTU SCT/pain.001, Lastschrift CDD = BTU SDD (COR)/pain.008.
 */
class LibraryEbicsGateway implements EbicsGateway {
    public function createKeys(EbicsConnection $connection): void {
        $manager = new ArrayKeyringManager();
        $secret = Str::random(48);
        $keyring = $manager->createKeyring(Keyring::VERSION_30);
        $keyring->setPassword($secret);
        $client = $this->client($connection, $keyring);
        $client->createUserSignatures();

        $connection->keyring_secret = $secret;
        $this->store($connection, $client->getKeyring());
    }

    public function sendInitialization(EbicsConnection $connection): void {
        $client = $this->client($connection);
        $this->call(fn () => $client->executeStandardOrder(new INI()));
        $this->call(fn () => $client->executeStandardOrder(new HIA()));
        $this->store($connection, $client->getKeyring());
    }

    public function letterKeys(EbicsConnection $connection): array {
        $client = $this->client($connection);
        $letter = (new EbicsBankLetter())->prepareBankLetter($client->getBank(), $client->getUser(), $client->getKeyring());

        return array_map(static fn (SignatureBankLetter $key): array => [
            'type' => $key->getType(),
            'version' => $key->getVersion(),
            'hash' => $key->getKeyHash(),
            'certificate_created_at' => $key->getCertificateCreatedAt()?->format('Y-m-d'),
        ], [$letter->getSignatureBankLetterA(), $letter->getSignatureBankLetterX(), $letter->getSignatureBankLetterE()]);
    }

    public function fetchBankKeys(EbicsConnection $connection): void {
        $client = $this->client($connection);
        $this->call(fn () => $client->executeInitializationOrder(new HPB()));
        $this->store($connection, $client->getKeyring());
    }

    public function downloadStatements(EbicsConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array {
        $client = $this->client($connection);
        $context = BTDContext::resolveInstance();
        $context->setServiceName('EOP')->setScope('DE')->setMsgName('camt.053')->setContainerType('ZIP');
        $context->setParserFormat(EbicsClientInterface::FILE_PARSER_FORMAT_ZIP_FILES);

        try {
            $result = $this->call(fn () => $client->executeDownloadOrder(new BTD($context, $from->startOfDay(), $to->endOfDay())));
        } catch (EbicsException $e) {
            if ($e->ebicsCode === '090005') {
                return [];
            }

            throw $e;
        }

        $files = array_map(static fn (XmlDocument|string $file): string => $file instanceof XmlDocument ? $file->getContent() : $file, $result->getDataFiles());

        return array_values(array_filter($files, static fn (string $file): bool => trim($file) !== ''));
    }

    public function uploadPayment(EbicsConnection $connection, PaymentRunKind $kind, string $xml, string $fileName): string {
        $client = $this->client($connection);
        $context = BTUContext::resolveInstance()->setFileName($fileName);
        match ($kind) {
            PaymentRunKind::CreditTransfer => $context->setServiceName('SCT')->setMsgName('pain.001'),
            PaymentRunKind::DirectDebit => $context->setServiceName('SDD')->setServiceOption('COR')->setMsgName('pain.008'),
        };

        $result = $this->call(fn () => $client->executeUploadOrder(new BTU($context, (new DocumentFactory())->createXml($xml))));

        return $client->getResponseHandler()->retrieveH00XResponseOrderId($result->getTransaction()->getInitialization()->getResponse());
    }

    public function suspend(EbicsConnection $connection): void {
        $client = $this->client($connection);
        $this->call(fn () => $client->executeUploadOrder(new SPR()));
    }

    private function client(EbicsConnection $connection, ?Keyring $keyring = null): EbicsClient {
        if ($keyring === null) {
            if ($connection->keyring === null || $connection->keyring_secret === null) {
                throw new EbicsException('no_keys');
            }
            try {
                $data = (array) JsonHelper::decode($connection->keyring);
            } catch (InvalidArgumentException) {
                throw new EbicsException('no_keys');
            }
            $keyring = (new ArrayKeyringManager())->loadKeyring($data, $connection->keyring_secret, Keyring::VERSION_30);
        }

        $bank = new Bank($connection->ebics_host, $connection->host_url);
        // EBICS 3.0 verlangt X.509-Zertifikate; in Deutschland genügen selbst ausgestellte.
        $generator = new BankX509Generator();
        $generator->setCertificateOptionsByBank($bank);
        $keyring->setCertificateGenerator($generator);

        // Eigene HTTP-Schicht: Zielprüfung beim Verbinden, Antwort ohne Entitätsauflösung (sf-1).
        $options = (new EbicsClientOptions)->setHttpClient(new GuardedEbicsHttpClient);

        return new EbicsClient($bank, new User($connection->ebics_partner, $connection->ebics_user), $keyring, $options);
    }

    private function store(EbicsConnection $connection, Keyring $keyring): void {
        $data = [];
        (new ArrayKeyringManager())->saveKeyring($keyring, $data);
        $connection->keyring = JsonHelper::encode($data);
        $connection->save();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function call(callable $call): mixed {
        try {
            return $call();
        } catch (NoDownloadDataAvailableException $e) {
            throw new EbicsException('no_data', '090005');
        } catch (EbicsResponseExceptionInterface $e) {
            throw new EbicsException('bank_rejected', $e->getResponseCode(), (string) ($e->getMeaning() ?? $e->getMessage()));
        }
    }
}
