<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainDnsService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Domain;

use App\Enums\Domain\{DomainCapabilityArea, DomainDnsRecordType};
use App\Models\Domain\{DomainDnsRecordProjection, DomainDnsZoneProjection, DomainProviderCommand, DomainProviderConnection};
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Nameserver-/DNS-Zonenverwaltung (Feature 083, MVP-389). Records werden
 * typisiert validiert und erst dann ins Provider-RR-Format serialisiert — die
 * Oberfläche nimmt keine rohen Befehlszeilen an. Vollständiger Replace und
 * additive Add/Del sind getrennte Aktionen; vor einem Replace wird die
 * gelesene Zone als Snapshot gehalten. Nach jeder Mutation liest WorkDiary die
 * Zone erneut und meldet Abweichungen als Konflikt.
 */
class DomainDnsService {
    public function __construct(
        private readonly DomainProviderResolver $resolver,
        private readonly DomainCommandService $commands,
    ) {}

    /** Liest die Zone und aktualisiert Zonen-/Record-Projektion. */
    public function readZone(DomainProviderConnection $connection, string $zone): DomainDnsZoneProjection {
        $adapter = $this->resolver->for($connection);
        $response = $adapter->execute('StatusDNSZone', ['dnszone' => $zone], DomainCapabilityArea::Dns);

        $zoneRow = DomainDnsZoneProjection::query()->updateOrCreate(
            [
                'organization_id' => $connection->organization_id,
                'connection_id' => $connection->id,
                'zone_hash' => DomainDnsZoneProjection::hashFor($zone),
            ],
            [
                'zone' => $zone,
                'soa' => ['soa' => $response->first('soa')],
                'revision' => $response->first('revision'),
                'raw_hash' => $response->rawHash(),
                'synced_at' => Carbon::now(),
            ],
        );

        // Records aus dem `rr`-Property neu materialisieren.
        $zoneRow->records()->delete();
        $position = 0;
        foreach ($response->property('rr') as $rr) {
            $parsed = $this->parseRr($rr);
            if ($parsed === null) {
                continue;
            }
            $zoneRow->records()->create($parsed + ['organization_id' => $connection->organization_id, 'position' => $position++, 'raw' => $rr]);
        }

        return $zoneRow->fresh(['records']) ?? $zoneRow;
    }

    /**
     * Validiert typisierte Records; wirft {@see DomainActionException} bei
     * Fehlern.
     *
     * @param  list<array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}>  $records
     */
    public function validateRecords(array $records): void {
        foreach ($records as $record) {
            $type = DomainDnsRecordType::tryFrom(strtoupper($record['type']));
            if ($type === null) {
                throw new DomainActionException(__('domain.errors.dns_type_invalid', ['type' => $record['type']]));
            }
            if (trim($record['name']) === '' || trim($record['content']) === '') {
                throw new DomainActionException(__('domain.errors.dns_incomplete'));
            }
            if ($type->hasPriority() && ($record['priority'] ?? null) === null) {
                throw new DomainActionException(__('domain.errors.dns_priority_required', ['type' => $type->value]));
            }
        }
    }

    /**
     * Vollständiger Replace (rrN) mit Snapshot der aktuellen Zone davor.
     *
     * @param  list<array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}>  $records
     * @return array{command: DomainProviderCommand, conflict: bool}
     */
    public function replaceZone(DomainProviderConnection $connection, string $zone, array $records, User $actor): array {
        $this->validateRecords($records);
        $snapshot = $this->readZone($connection, $zone); // Snapshot vor dem Replace

        $params = ['dnszone' => $zone];
        foreach ($records as $i => $record) {
            $params['rr' . $i] = $this->serialize($record);
        }

        $command = $this->commands->create(
            $connection,
            DomainCapabilityArea::Dns,
            'ModifyDNSZone',
            $zone,
            $params,
            false,
            $snapshot,
            null,
            ['records' => $snapshot->records->map->only(['type', 'name', 'ttl', 'priority', 'content'])->all()],
            $actor,
        );
        $this->commands->dispatch($command);

        return ['command' => $command, 'conflict' => $this->hasConflict($connection, $zone, $records)];
    }

    /**
     * Additive Änderung (addrrN/delrrN) — getrennt vom vollständigen Replace.
     *
     * @param  list<array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}>  $add
     * @param  list<array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}>  $delete
     * @return array{command: DomainProviderCommand, conflict: bool}
     */
    public function modifyRecords(DomainProviderConnection $connection, string $zone, array $add, array $delete, User $actor): array {
        $this->validateRecords($add);
        $this->validateRecords($delete);

        $params = ['dnszone' => $zone];
        foreach ($add as $i => $record) {
            $params['addrr' . $i] = $this->serialize($record);
        }
        foreach ($delete as $i => $record) {
            $params['delrr' . $i] = $this->serialize($record);
        }

        $command = $this->commands->createAndDispatch(
            $connection,
            DomainCapabilityArea::Dns,
            'ModifyDNSZone',
            $zone,
            $params,
            null,
            null,
            null,
            $actor,
        );

        $this->readZone($connection, $zone); // Nachkontrolle

        return ['command' => $command, 'conflict' => false];
    }

    /**
     * Serialisiert einen Record ins Provider-RR-Format.
     *
     * @param  array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}  $record
     */
    public function serialize(array $record): string {
        $type = strtoupper($record['type']);
        $ttl = (int) ($record['ttl'] ?? 3600);
        $prefix = sprintf('%s %d IN %s', trim($record['name']), $ttl, $type);
        if (($record['priority'] ?? null) !== null) {
            $prefix .= ' ' . (int) $record['priority'];
        }

        return $prefix . ' ' . trim($record['content']);
    }

    /**
     * @param  list<array{type: string, name: string, ttl?: int|null, priority?: int|null, content: string}>  $expected
     */
    private function hasConflict(DomainProviderConnection $connection, string $zone, array $expected): bool {
        $fresh = $this->readZone($connection, $zone);
        $actual = $fresh->records->map(fn (DomainDnsRecordProjection $r): string => $this->serialize([
            'type' => $r->type->value,
            'name' => $r->name,
            'ttl' => $r->ttl,
            'priority' => $r->priority,
            'content' => $r->content,
        ]))->sort()->values()->all();

        $want = collect($expected)->map(fn (array $r): string => $this->serialize($r))->sort()->values()->all();

        return $actual !== $want;
    }

    /** @return array{type: string, name: string, ttl: int|null, priority: int|null, content: string}|null */
    private function parseRr(string $rr): ?array {
        // Format: NAME TTL IN TYPE [PRIORITY] CONTENT
        $parts = preg_split('/\s+/', trim($rr)) ?: [];
        if (count($parts) < 5) {
            return null;
        }
        $name = $parts[0];
        $ttl = is_numeric($parts[1]) ? (int) $parts[1] : null;
        $type = DomainDnsRecordType::tryFrom(strtoupper($parts[3]));
        if ($type === null) {
            return null;
        }
        $rest = array_slice($parts, 4);
        $priority = null;
        if ($type->hasPriority() && is_numeric($rest[0])) {
            $priority = (int) array_shift($rest);
        }

        return [
            'type' => $type->value,
            'name' => $name,
            'ttl' => $ttl,
            'priority' => $priority,
            'content' => implode(' ', $rest),
        ];
    }
}
