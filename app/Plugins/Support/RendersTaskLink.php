<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RendersTaskLink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\Integration\ExternalReference;
use App\Models\Project\Task;

/**
 * Link zur Quellaufgabe im Aufgabendialog (Slot `task-dialog.links`) für
 * Plugins, die Aufgaben importieren ({@see \App\Plugins\Contracts\SlotRenderer}
 * mit {@see \App\Plugins\Contracts\LinksExternalRecords}; Konsolidierung
 * kon-04): die Referenz des Plugins an der Aufgabe, die URL über
 * `externalRecordUrl()`, die Beschriftung aus `<id>::<id>.task_link`.
 */
trait RendersTaskLink {
    /** `external_type` der Referenz, die eine Aufgabe mit ihrer Quelle verbindet. */
    abstract protected function taskLinkType(): string;

    public function renderActions(string $slot, mixed $context = null): ?string {
        if ($slot !== 'task-dialog.links' || ! $context instanceof Task || ! $this->isEnabled()) {
            return null;
        }
        $reference = ExternalReference::query()
            ->forPlugin($context->organization_id, static::ID, $this->taskLinkType())
            ->forReferenceable($context)
            ->first();
        $url = $reference !== null ? $this->externalRecordUrl($reference) : null;

        return $url !== null
            ? view('plugins.task-import._link', ['url' => $url, 'label' => (string) __(static::ID . '::' . static::ID . '.task_link')])->render()
            : null;
    }
}
