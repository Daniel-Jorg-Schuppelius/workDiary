<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NextcloudIntakeUploadChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Nextcloud\Services;

use App\Models\Customer\{CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\Organization;
use App\Plugins\Nextcloud\Api\NextcloudWebdavClient;
use App\Plugins\Nextcloud\Contracts\NextcloudTransportFactory;
use App\Plugins\Nextcloud\Exceptions\NextcloudNotFoundException;
use App\Plugins\Nextcloud\NextcloudConfig;
use App\Services\Customer\Contracts\IntakeUploadChannel;
use App\Services\Customer\Dto\{IntakeRemoteFile, IntakeUploadLinkData};
use Carbon\CarbonImmutable;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SensitiveParameter;

/**
 * Nextcloud als Upload-Kanal des Kundeneingangs (MVP-1078): Ordner
 * `<Basisordner>/<Vorgangsnummer>` und eine öffentliche Freigabe „nur
 * hochladen" (File Drop) mit Passwort und Ablauf. Eigene Zugangsdaten in den
 * Plugin-Einstellungen — getrennt von Dokumenteingang und Backupziel.
 */
class NextcloudIntakeUploadChannel implements IntakeUploadChannel {
    public const KEY = 'nextcloud';

    public function __construct(private readonly NextcloudTransportFactory $transport) {}

    public function key(): string {
        return self::KEY;
    }

    public function label(): string {
        return 'Nextcloud';
    }

    public function isAvailable(Organization $organization): bool {
        $config = NextcloudConfig::intakeUpload((int) $organization->id);

        return $config['enabled'] && $config['server_url'] !== null && $config['username'] !== null && $config['app_password'] !== null;
    }

    public function linkLifetimeDays(Organization $organization): int {
        return NextcloudConfig::intakeUpload((int) $organization->id)['link_days'];
    }

    public function createLink(CustomerIntake $intake, #[SensitiveParameter] string $password, CarbonImmutable $expiresAt): IntakeUploadLinkData {
        $client = $this->client((int) $intake->organization_id);
        $folder = trim(NextcloudConfig::intakeUpload((int) $intake->organization_id)['base_folder'], '/') . '/' . (preg_replace('/[^A-Za-z0-9._-]/', '-', $intake->number) ?? $intake->number);
        $client->ensureCollection($folder);
        $share = $client->createUploadShare($folder, $password, $expiresAt->toDateString(), $intake->number);

        return new IntakeUploadLinkData($share['id'], $share['url'], $folder);
    }

    public function revokeLink(CustomerIntakeUploadLink $link): void {
        if ($link->external_id === null) {
            return;
        }
        if (! $this->client((int) $link->organization_id)->deleteShare($link->external_id)) {
            throw new RuntimeException('Nextcloud share could not be deleted.');
        }
    }

    public function listFiles(CustomerIntakeUploadLink $link): array {
        try {
            $children = $this->client((int) $link->organization_id)->listChildren($link->folder);
        } catch (NextcloudNotFoundException) {
            return [];
        }

        $files = [];
        foreach ($children as $child) {
            if ($child['is_dir'] || $child['fileid'] === '') {
                continue;
            }
            $files[] = new IntakeRemoteFile(
                $child['fileid'] . ':' . $child['etag'],
                $child['path'],
                basename($child['path']),
                $child['size'],
                $child['mime'],
            );
        }

        return $files;
    }

    public function download(CustomerIntakeUploadLink $link, IntakeRemoteFile $file): StreamInterface {
        return $this->client((int) $link->organization_id)->getStream($file->path);
    }

    private function client(int $organizationId): NextcloudWebdavClient {
        $config = NextcloudConfig::intakeUpload($organizationId);
        if ($config['server_url'] === null || $config['username'] === null || $config['app_password'] === null) {
            throw new RuntimeException('Nextcloud upload channel is not configured.');
        }

        return $this->transport->forCredentials($config['server_url'], $config['username'], $config['app_password']);
    }
}
