<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WarehouseKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art eines Lagerorts (Feature 048, MVP-706): festes Lager oder ein Lager
 * mit Bezug auf Standort, Fahrzeug (Montagewagen) oder Team. Der Bezug ist
 * nur bei der passenden Art gesetzt; Bestandslogik ist artunabhängig.
 */
enum WarehouseKind: string implements HasLabel {
    use HasOptions;

    case Fixed = 'fixed';
    case Vehicle = 'vehicle';
    case Site = 'site';
    case Team = 'team';

    public function label(): string {
        return match ($this) {
            self::Fixed => __('inventory.kind.fixed'),
            self::Vehicle => __('inventory.kind.vehicle'),
            self::Site => __('inventory.kind.site'),
            self::Team => __('inventory.kind.team'),
        };
    }

    /** Spalte auf `warehouses`, die den Bezug dieser Art trägt (null bei festem Lager). */
    public function referenceColumn(): ?string {
        return match ($this) {
            self::Fixed => null,
            self::Vehicle => 'vehicle_id',
            self::Site => 'site_id',
            self::Team => 'team_id',
        };
    }
}
