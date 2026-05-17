<?php

/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncArticlesCommand.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

namespace App\Console\Commands;

use App\Models\Organization;
use App\Plugins\Lexoffice\LexofficeArticleSync;
use Illuminate\Console\Command;

class LexofficeSyncArticlesCommand extends Command
{
    protected $signature = 'lexoffice:sync-articles {--organization= : ID einer einzelnen Organisation, sonst alle}';

    protected $description = 'Synchronisiert Lexoffice-Artikel (Services/Produkte) in die lokale Tabelle `lexoffice_articles`.';

    public function handle(): int
    {
        $apiKey = config('plugins.lexoffice.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            $this->error('LEXOFFICE_API_KEY ist nicht konfiguriert.');

            return self::FAILURE;
        }

        $sync = new LexofficeArticleSync($apiKey);

        $orgId = $this->option('organization');
        $query = Organization::query();
        if ($orgId !== null && $orgId !== '') {
            $query->whereKey((int) $orgId);
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        foreach ($organizations as $org) {
            $this->info("Sync Lexoffice-Artikel für Organisation #{$org->id} ({$org->name})...");
            try {
                $result = $sync->sync($org);
                $this->line("  created: {$result['created']}, updated: {$result['updated']}, archived: {$result['archived']}");
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
