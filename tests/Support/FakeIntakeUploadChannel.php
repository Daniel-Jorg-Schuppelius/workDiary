<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FakeIntakeUploadChannel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Customer\{CustomerIntake, CustomerIntakeUploadLink};
use App\Models\Platform\Organization;
use App\Services\Customer\Contracts\IntakeUploadChannel;
use App\Services\Customer\Dto\{IntakeRemoteFile, IntakeUploadLinkData};
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use SensitiveParameter;

/**
 * Test-Kanal für den Upload-Link des Kundeneingangs (MVP-1078): Zustand in
 * statischen Feldern, damit der Container ihn je Aufruf neu bauen darf.
 */
final class FakeIntakeUploadChannel implements IntakeUploadChannel {
    public static bool $available = true;

    public static bool $failList = false;

    /** @var array<string, array{file: IntakeRemoteFile, content: string}> */
    public static array $files = [];

    /** @var list<string> */
    public static array $revoked = [];

    public static function reset(): void {
        self::$available = true;
        self::$failList = false;
        self::$files = [];
        self::$revoked = [];
    }

    /** Datei im Ordner ablegen; `$size` überschreibt die gemeldete Größe. */
    public static function put(string $name, string $content, string $id, string $etag = 'e1', ?int $size = null): void {
        $key = $id . ':' . $etag;
        self::$files[$key] = ['file' => new IntakeRemoteFile($key, 'ablage/' . $name, $name, $size ?? strlen($content)), 'content' => $content];
    }

    public function key(): string {
        return 'fake';
    }

    public function label(): string {
        return 'Testablage';
    }

    public function isAvailable(Organization $organization): bool {
        return self::$available;
    }

    public function linkLifetimeDays(Organization $organization): int {
        return 14;
    }

    public function createLink(CustomerIntake $intake, #[SensitiveParameter] string $password, CarbonImmutable $expiresAt): IntakeUploadLinkData {
        return new IntakeUploadLinkData('share-' . $intake->id, 'https://cloud.example.test/s/' . $intake->id, 'Kundeneingaenge/' . $intake->number);
    }

    public function revokeLink(CustomerIntakeUploadLink $link): void {
        self::$revoked[] = (string) $link->external_id;
    }

    public function listFiles(CustomerIntakeUploadLink $link): array {
        if (self::$failList) {
            throw new RuntimeException('Ablage nicht erreichbar');
        }

        return array_values(array_map(static fn (array $entry): IntakeRemoteFile => $entry['file'], self::$files));
    }

    public function download(CustomerIntakeUploadLink $link, IntakeRemoteFile $file): StreamInterface {
        return Utils::streamFor(self::$files[$file->key]['content']);
    }
}
