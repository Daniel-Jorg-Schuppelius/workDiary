<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserExportSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Export\Specs;

use App\Enums\Export\ExportEntity;
use App\Models\{Organization, User};
use Illuminate\Database\Eloquent\Model;

/**
 * Export-Spezifikation für Benutzer — Round-Trip zur {@see \App\Services\Import\Specs\UserSpec}.
 *
 * Es werden ausschließlich unkritische Stammdaten exportiert (kein Passwort,
 * keine Rollen), passend zum Import-Verhalten.
 *
 * Filter:
 * - `q`: Freitextsuche über Name / E-Mail
 */
class UserExportSpec extends AbstractExportSpec {
    public function entity(): ExportEntity {
        return ExportEntity::Users;
    }

    public function columns(): array {
        return ['name', 'personnel_number', 'email', 'hourly_rate', 'internal_rate', 'home_address'];
    }

    public function query(Organization $organization, array $filters): iterable {
        $search = trim((string) ($filters['q'] ?? ''));

        return User::query()
            ->where('organization_id', $organization->id)
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('personnel_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->cursor();
    }

    public function toRow(Model $model): array {
        /** @var User $model */
        return [
            'name' => $this->str($model->name),
            'personnel_number' => $this->str($model->personnel_number),
            'email' => $this->str($model->email),
            'hourly_rate' => $this->decimalCell($model->hourly_rate),
            'internal_rate' => $this->decimalCell($model->internal_rate),
            'home_address' => $this->str($model->home_address),
        ];
    }
}
