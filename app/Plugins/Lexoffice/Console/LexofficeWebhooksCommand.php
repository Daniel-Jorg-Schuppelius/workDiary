<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeWebhooksCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\PluginSetting;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin, LexofficeWebhookService};
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Richtet die Lexoffice-Event-Subscriptions je Organisation ein (Audit
 * 2026-08, Welle 1.3): generiert bei Bedarf das URL-Token (`webhook_secret`
 * in plugin_settings), legt fehlende Abos an bzw. räumt sie mit --remove ab.
 * Die Callback-URL braucht eine öffentlich erreichbare APP_URL.
 */
class LexofficeWebhooksCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:webhooks '
        . self::ORGANIZATION_OPTION
        . ' {--remove : Subscriptions dieser Callback-URL entfernen statt anlegen}';

    protected $description = 'Richtet Lexoffice-Event-Subscriptions (Webhooks) je Organisation ein bzw. entfernt sie.';

    public function handle(): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($organizations as $org) {
            $config = LexofficeConfig::resolve($org->id);
            if (! $config['enabled'] || ! is_string($config['api_key']) || $config['api_key'] === '') {
                continue;
            }

            $secret = is_string($config['webhook_secret']) && $config['webhook_secret'] !== ''
                ? $config['webhook_secret']
                : $this->generateSecret((int) $org->id);

            $callbackUrl = route('api.webhooks.lexoffice', ['organization' => $org->id, 'token' => $secret]);
            $service = new LexofficeWebhookService($config['api_key'], $config['base_url']);

            $this->info("Lexoffice-Webhooks für Organisation #{$org->id} ({$org->name})...");
            try {
                if ($this->option('remove')) {
                    $removed = $service->removeSubscriptions($callbackUrl);
                    $this->line("  entfernt: {$removed}");
                } else {
                    $result = $service->ensureSubscriptions($callbackUrl);
                    $this->line("  angelegt: {$result['created']}, bestehend: {$result['existing']}");
                }
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  Fehler: {$e->getMessage()}");
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Token erzeugen und in den Plugin-Einstellungen der Org hinterlegen. */
    private function generateSecret(int $organizationId): string {
        $secret = Str::random(48);

        $setting = PluginSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', LexofficePlugin::ID)
            ->first();

        if ($setting === null) {
            PluginSetting::query()->create([
                'organization_id' => $organizationId,
                'plugin_id' => LexofficePlugin::ID,
                'enabled' => true,
                'settings' => ['webhook_secret' => $secret],
            ]);
        } else {
            $settings = is_array($setting->settings) ? $setting->settings : [];
            $settings['webhook_secret'] = $secret;
            $setting->forceFill(['settings' => $settings])->save();
        }

        return $secret;
    }
}
