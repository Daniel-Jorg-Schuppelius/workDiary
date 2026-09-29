<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OneNoteImportSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Services;

use App\Models\Platform\Organization;
use App\Models\Plugins\Msgraph\MsgraphOneNoteConnection;
use App\Plugins\Msgraph\Api\MsgraphOneNoteClient;
use App\Plugins\Msgraph\{MsgraphConfig, MsgraphPlugin};
use App\Services\Collections\Import\Contracts\NotebookSource;
use RuntimeException;

/** OneNote-Übernahme (MVP-815) als Notizbuch-Quelle der Wissensübernahme. */
final class OneNoteImportSource implements NotebookSource {
    public function __construct(private readonly OneNoteNotebookReader $reader) {}

    public function key(): string {
        return 'onenote';
    }

    public function icon(): string {
        return 'book';
    }

    public function pluginId(): string {
        return MsgraphPlugin::ID;
    }

    public function referenceType(): string {
        return 'onenote_page';
    }

    public function ready(Organization $organization): bool {
        return $this->connection($organization) !== null;
    }

    public function notebooks(Organization $organization): array {
        return (new MsgraphOneNoteClient($this->requireConnection($organization)))->notebooks();
    }

    public function documents(Organization $organization, string $notebookId, string $notebookName, int $limit, callable $known): array {
        return $this->reader->documents(new MsgraphOneNoteClient($this->requireConnection($organization)), $notebookId, $notebookName, $limit, $known);
    }

    public function markImported(Organization $organization): void {
        $this->connection($organization)?->forceFill(['last_import_at' => now()])->save();
    }

    private function connection(Organization $organization): ?MsgraphOneNoteConnection {
        if (! MsgraphConfig::oneNoteImportEnabled((int) $organization->id)) {
            return null;
        }
        $connection = MsgraphOneNoteConnection::query()->where('organization_id', $organization->id)->first();

        return $connection instanceof MsgraphOneNoteConnection && $connection->isActive() ? $connection : null;
    }

    private function requireConnection(Organization $organization): MsgraphOneNoteConnection {
        return $this->connection($organization) ?? throw new RuntimeException('OneNote-Verbindung nicht aktiv.');
    }
}
