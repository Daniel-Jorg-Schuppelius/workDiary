<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTimelinePresenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use Illuminate\Support\Carbon;

/**
 * Formatiert rohe Timeline-Events des {@see AssetTimelineService} für die
 * Detailansicht (Titel/Detail/Zeitstempel je Event-Art). Reine
 * Präsentationslogik — aus dem AssetController extrahiert
 * (Refactoring Welle 2, B6b).
 */
class AssetTimelinePresenter {
    /**
     * @param  array<string, mixed>  $event
     * @return array{kind: string, title: string, detail: string, occurred_at_formatted: string}
     */
    public function present(array $event): array {
        $kind = (string) ($event['kind'] ?? '');
        $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
        $auditEvent = (string) ($payload['event'] ?? '');

        $title = match ($kind) {
            'order.linked' => __('Auftrag verknüpft'),
            'protocol.linked' => __('Protokoll verknüpft'),
            'material.linked' => __('Materialeinsatz verknüpft'),
            'attachment.linked' => __('Anhang verknüpft'),
            'asset.audit' => $this->assetAuditTitle($auditEvent),
            default => __('Ereignis'),
        };

        $detail = match ($kind) {
            'order.linked' => (string) ($payload['title'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'protocol.linked' => (string) ($payload['title'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'material.linked' => (string) ($payload['description'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'attachment.linked' => (string) ($payload['name'] ?? ('#' . ((int) ($payload['id'] ?? 0)))),
            'asset.audit' => $this->assetAuditDetail($payload),
            default => __('Unbekanntes Ereignis'),
        };

        return [
            'kind' => $kind,
            'title' => $title,
            'detail' => $detail,
            'occurred_at_formatted' => $this->formatTimelineDate($event['occurred_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assetAuditDetail(array $payload): string {
        $name = trim((string) ($payload['user_name'] ?? ''));

        return $name !== '' ? __('durch :name', ['name' => $name]) : '';
    }

    private function assetAuditTitle(string $auditEvent): string {
        return match ($auditEvent) {
            'asset.statusChanged' => __('Status geändert'),
            'asset.decommissioned' => __('Außer Betrieb gesetzt'),
            'asset.ownershipTransferred' => __('Eigentum übertragen'),
            'asset.moved' => __('Standort geändert'),
            'asset.updated' => __('Asset aktualisiert'),
            'asset.created' => __('Asset angelegt'),
            'created' => __('Datensatz angelegt'),
            'updated' => __('Datensatz geändert'),
            'deleted' => __('Datensatz gelöscht'),
            default => __('Asset-Ereignis'),
        };
    }

    private function formatTimelineDate(mixed $value): string {
        if (! is_string($value) || trim($value) === '') {
            return '—';
        }

        return Carbon::parse($value)->format('d.m.Y H:i');
    }
}
