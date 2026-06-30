<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\RemoteSupport;

use App\Models\{Asset, Organization};
use App\Services\Integration\InboxGroupBooker;
use Illuminate\Support\Collection;

/**
 * Bindet die offenen Fernwartungs-Gruppen unbekannter Geräte (provider+remote_id)
 * an die universelle Zuordnungs-Inbox. Liest bewusst aus der bestehenden
 * {@see \App\Models\RemotePendingSession}-Inbox des Plugins und delegiert Buchung
 * (Geräte-ID → Asset binden + Sitzungen materialisieren) an den bewährten
 * {@see RemoteSupportService} — ohne Storage-Umbau.
 *
 * Form-Typ `asset`: Bindung an ein BESTEHENDES Asset. Neuanlage eines Geräts und
 * der Mehrkundengeräte-Flow (Shared-Sessions → Kunde/Projekt) bleiben in der
 * RemoteSupport-Oberfläche (Deep-Link).
 */
class RemoteSupportGroupBooker implements InboxGroupBooker {
    public function __construct(private readonly RemoteSupportService $service) {}

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = $this->service->openPendingGroups($organization)->map(function (object $group): array {
            return [
                'plugin_id' => RemoteSupportPlugin::ID,
                'form' => 'asset',
                'group_key' => $group->provider . '|' . $group->remote_id,
                'provider' => $group->provider,
                'remote_id' => $group->remote_id,
                'alias' => $group->alias,
                'count' => $group->count,
                'minutes' => $group->minutes,
                'first_seen' => $group->first_seen,
                'last_seen' => $group->last_seen,
            ];
        })->values();

        return $groups;
    }

    public function rules(): array {
        return [
            'asset' => ['required', 'string'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        [$provider, $remoteId] = $this->split($groupKey);

        $asset = (new Asset)->resolveRouteBinding((string) ($input['asset'] ?? ''));
        abort_unless($asset instanceof Asset, 404);
        abort_unless((int) $asset->organization_id === (int) $organization->id, 404);

        return $this->service->assignPending($organization, $provider, $remoteId, $asset);
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        [$provider, $remoteId] = $this->split($groupKey);

        return $this->service->dismissPending($organization, $provider, $remoteId);
    }

    /**
     * @return array{0: string, 1: string}  [provider, remote_id]
     */
    private function split(string $groupKey): array {
        $parts = explode('|', $groupKey, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
