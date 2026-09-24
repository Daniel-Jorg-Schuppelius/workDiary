<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Retention;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/** Löschbereiche des Moduls (Aufbewahrung, Feature 130) — gemeldet über das Manifest (MVP-863). */
final class CustomerRetentionPolicies implements RetentionPolicyProvider {
    public function policies(): array {
        return [
            // Kundenstammdaten ohne Geschäftsvorfälle (Feature 130, MVP-694 —
            // H21): nur Review-Ausweis — Kandidaten sind Kunden OHNE Belege/
            // Zeiten (keine Rechnungen, keine Diary-Einträge, keine Zeiten auf
            // Kundenprojekten) und ohne Kontakt seit Frist (updated_at als
            // Anker). Strukturdaten (echte Projekte/Standorte/Assets/Portal-
            // Konten) blocken als Ausnahme — das vom CustomerObserver
            // auto-angelegte Standardprojekt zählt NICHT als Struktur und wird
            // beim Purge (nur leer) mitentfernt, sonst bliebe eine verwaiste
            // Hülle (FK SET NULL). Gelöscht wird ausschließlich nach der
            // zweistufigen Bestätigung.
            new RetentionPolicy(
                area: 'customer_master',
                modelClass: \App\Models\Customer\Customer::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Customer\Customer::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('updated_at', '<', $cutoff)
                    ->whereNotExists(fn($query) => $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('diary_entries')->whereColumn('diary_entries.customer_id', 'customers.id'))
                    ->whereNotExists(fn($query) => $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('invoices')->whereColumn('invoices.customer_id', 'customers.id'))
                    ->whereNotExists(fn($query) => $query->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('time_entries')
                        ->join('projects', 'projects.id', '=', 'time_entries.project_id')
                        ->whereColumn('projects.customer_id', 'customers.id')),
                exempt: function (\App\Models\Customer\Customer $subject): ?string {
                    $structures = [
                        'Projekte' => \Illuminate\Support\Facades\DB::table('projects')->where('customer_id', $subject->id)->where('is_default', false),
                        'Standorte' => \Illuminate\Support\Facades\DB::table('sites')->where('customer_id', $subject->id),
                        'Assets' => \Illuminate\Support\Facades\DB::table('assets')->where('customer_id', $subject->id),
                        'Portal-Konten' => \Illuminate\Support\Facades\DB::table('users')->where('customer_id', $subject->id),
                    ];
                    foreach ($structures as $label => $query) {
                        if ($query->exists()) {
                            return "Verknüpfte Strukturdaten vorhanden ({$label}) — zuerst bereinigen.";
                        }
                    }

                    return null;
                },
                purge: function (\App\Models\Customer\Customer $subject): void {
                    \Illuminate\Support\Facades\DB::table('projects')
                        ->where('customer_id', $subject->id)
                        ->where('is_default', true)
                        ->whereNotExists(fn($query) => $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('time_entries')->whereColumn('time_entries.project_id', 'projects.id'))
                        ->delete();
                    $subject->delete();
                },
            ),
        ];
    }
}
