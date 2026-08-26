<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNotesSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{Customer, Lead};
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Kommunikationsjournal (Anrufe/E-Mails/Termine) zum Betroffenen — Zähler + Zeitraum. */
class CommunicationNotesSection extends AbstractSubjectSection {
    public function key(): string {
        return 'communication';
    }

    public function title(): string {
        return __('Kommunikation (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        if (! $subject instanceof Customer && ! $subject instanceof Lead) {
            throw new InvalidArgumentException(self::class . ' erwartet Customer oder Lead.');
        }

        return ['families' => [
            $this->family(
                'communication_notes',
                __('Kommunikationsnotizen'),
                \App\Models\CommunicationNote::query()->withoutGlobalScopes()
                    ->where('organization_id', (int) $subject->getAttribute('organization_id'))
                    ->where('notable_type', $subject->getMorphClass())
                    ->where('notable_id', $subject->getKey()),
                'occurred_at',
            ),
        ]];
    }
}
