<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConnectionHealthModels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diagnostics;

use Illuminate\Database\Eloquent\Model;

/**
 * Verbindungsmodelle mit {@see \App\Models\Concerns\HasConnectionHealth} für
 * Diagnose und Ablaufprüfung (MVP-1044). Kernmodelle stehen hier, Plugins
 * tragen ihre beim Booten ein.
 */
final class ConnectionHealthModels {
    /** @var array<string, array{model: class-string<Model>, task: bool}> */
    private array $models = [
        'email' => ['model' => \App\Models\Mail\EmailConnection::class, 'task' => true],
        'cti' => ['model' => \App\Models\Cti\CtiConnection::class, 'task' => true],
        'carrier' => ['model' => \App\Models\Shipping\CarrierConnection::class, 'task' => true],
        'cloud_documents' => ['model' => \App\Models\CloudIntake\CloudDocumentConnection::class, 'task' => false],
        'domain_provider' => ['model' => \App\Models\Domain\DomainProviderConnection::class, 'task' => false],
        'ai_provider' => ['model' => \App\Models\Ai\AiProviderConnection::class, 'task' => false],
        'backup_target' => ['model' => \App\Models\Backup\BackupTargetConnection::class, 'task' => false],
    ];

    /**
     * @param  class-string<Model>  $model
     * @param  bool  $operationsTask  Störung zusätzlich als Betriebsaufgabe melden
     */
    public function register(string $key, string $model, bool $operationsTask = false): void {
        $this->models[$key] = ['model' => $model, 'task' => $operationsTask];
    }

    /** @return array<string, class-string<Model>> */
    public function all(): array {
        return array_map(static fn (array $entry): string => $entry['model'], $this->models);
    }

    /** @return array<string, class-string<Model>> */
    public function withOperationsTask(): array {
        return array_map(static fn (array $entry): string => $entry['model'], array_filter($this->models, static fn (array $entry): bool => $entry['task']));
    }
}
