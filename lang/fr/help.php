<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : help.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Centre d'aide (fonctionnalité 039, MVP-752) : rubriques de la page d'aperçu.
return [
    // Artikelschema der Pilotartikel (MVP-756) — Reihenfolge wie
    // config('help-center.article_schema').
    'schema' => [
        'zweck' => 'Objectif et contexte',
        'voraussetzungen' => 'Prérequis',
        'ablauf' => 'Déroulement recommandé',
        'beispiel' => 'Exemple pratique',
        'fehler' => 'Erreurs fréquentes',
        'naechste-schritte' => 'Effets et prochaines étapes',
    ],
    'sections' => [
        'erste-schritte' => [
            'title' => 'Premiers pas',
            'description' => 'Connexion, tableau de bord, navigation, réglages personnels et les premières étapes essentielles.',
        ],
        'kunden-vertrieb' => [
            'title' => 'Clients & ventes',
            'description' => 'Fiches clients, dossier client, projets, portail client, rendez-vous et sujets commerciaux.',
        ],
        'zeit-personal' => [
            'title' => 'Temps & personnel',
            'description' => 'Pointeuse, saisies de temps, absences, planification des services, comptes de temps et export de paie.',
        ],
        'auftraege-service' => [
            'title' => 'Commandes & service',
            'description' => 'Journal des interventions, procès-verbaux, procédures, formulaires, helpdesk et sujets de chantier.',
        ],
        'material-lager' => [
            'title' => 'Articles & stock',
            'description' => 'Base articles, catalogues, stock, approvisionnement, prix et numéros de série.',
        ],
        'geraete-fuhrpark' => [
            'title' => 'Équipements & parc',
            'description' => 'Dossier des équipements, contrôles, véhicules, remises de clés, garanties et logiciels.',
        ],
        'faktura' => [
            'title' => 'Factures & facturation',
            'description' => 'Devis, factures, facturation électronique, contrats, flux de pièces et commissions.',
        ],
        'buchhaltung' => [
            'title' => 'Comptabilité & finances',
            'description' => 'Journal, plan comptable, clôture, comptes bancaires, export DATEV et export des temps.',
        ],
        'auswertungen' => [
            'title' => 'Analyses',
            'description' => 'Rapports, analyses détaillées, exports et bonne lecture des indicateurs.',
        ],
        'sicherheit-compliance' => [
            'title' => 'Sécurité & conformité',
            'description' => 'SMSI, protection des données, alerte professionnelle, sécurité au travail, audit et archives.',
        ],
        'administration' => [
            'title' => 'Administration',
            'description' => 'Organisation, rôles et droits, import, sauvegarde, licence et intégrations.',
        ],
        'weitere' => [
            'title' => 'Autres sujets',
            'description' => 'Tout ce qui ne relève d’aucun des domaines principaux.',
        ],
    ],
];
