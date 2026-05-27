<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Software;

use App\Exceptions\SoftwareInstallationException;
use App\Models\{Asset, Software, SoftwareInstallation, User};

class SoftwareInstallationService {
    /** @param array<string, mixed> $payload */
    public function attach(Asset $asset, Software $software, User $actor, array $payload): SoftwareInstallation {
        $this->ensureSameOrganization($asset, $software);

        $isOs = (bool) ($payload['is_operating_system'] ?? false);
        if ($isOs) {
            $this->ensureNoExistingOs($asset);
        }

        $install = SoftwareInstallation::query()->create([
            'organization_id' => (int) $asset->organization_id,
            'asset_id' => $asset->id,
            'software_id' => $software->id,
            'version' => $payload['version'] ?? $software->default_version,
            'license_key' => $payload['license_key'] ?? null,
            'seats' => isset($payload['seats']) && $payload['seats'] !== '' ? (int) $payload['seats'] : null,
            'installed_on' => $payload['installed_on'] ?? null,
            'expires_on' => $payload['expires_on'] ?? null,
            'is_operating_system' => $isOs,
            'notes' => $payload['notes'] ?? null,
        ]);

        $install->audit('softwareInstallation.attached', [
            'actor_id' => $actor->id,
            'asset_id' => $asset->id,
            'software_id' => $software->id,
            'is_operating_system' => $isOs,
        ]);

        return $install->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function update(SoftwareInstallation $install, User $actor, array $payload): SoftwareInstallation {
        if (array_key_exists('is_operating_system', $payload)) {
            $nextIsOs = (bool) $payload['is_operating_system'];
            if ($nextIsOs && ! $install->is_operating_system) {
                $this->ensureNoExistingOs($install->asset()->firstOrFail(), $install->id);
            }
        }

        $assignable = [
            'version',
            'license_key',
            'seats',
            'installed_on',
            'expires_on',
            'is_operating_system',
            'notes',
        ];
        foreach ($assignable as $key) {
            if (array_key_exists($key, $payload)) {
                $install->{$key} = $key === 'seats'
                    ? ($payload[$key] === null || $payload[$key] === '' ? null : (int) $payload[$key])
                    : $payload[$key];
            }
        }
        $install->save();

        $install->audit('softwareInstallation.updated', [
            'actor_id' => $actor->id,
            'is_operating_system' => (bool) $install->is_operating_system,
        ]);

        return $install->refresh();
    }

    public function detach(SoftwareInstallation $install, User $actor): void {
        $payload = [
            'actor_id' => $actor->id,
            'asset_id' => $install->asset_id,
            'software_id' => $install->software_id,
            'is_operating_system' => (bool) $install->is_operating_system,
        ];
        $install->audit('softwareInstallation.detached', $payload);
        $install->delete();
    }

    private function ensureSameOrganization(Asset $asset, Software $software): void {
        if ((int) $asset->organization_id !== (int) $software->organization_id) {
            throw SoftwareInstallationException::organizationMismatch();
        }
    }

    private function ensureNoExistingOs(Asset $asset, ?int $excludeInstallationId = null): void {
        $exists = SoftwareInstallation::query()
            ->where('asset_id', $asset->id)
            ->where('is_operating_system', true)
            ->when($excludeInstallationId !== null, fn($q) => $q->where('id', '!=', $excludeInstallationId))
            ->exists();

        if ($exists) {
            throw SoftwareInstallationException::osAlreadyExists();
        }
    }
}
