<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : wage_types.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => "Rubriques de paie & transmission des exports",
        'index_subtitle' => "Faire correspondre les rubriques de paie internes aux numéros de rubriques du programme de paie cible et configurer la transmission automatique par profil d'export.",
        'mappings_help' => "Comment fonctionne la correspondance des rubriques de paie ?",
        'mappings_help_text' => "Lors de l'export des temps, la rubrique de chaque ligne est résolue d'abord via cette correspondance, puis via la rubrique de la règle de majoration ; les heures normales sans correspondance conservent la rubrique par défaut du profil. Si une ligne de majoration ou d'absence n'a aucune affectation, l'export s'interrompt avec un message d'erreur au lieu de produire un fichier erroné.",
        'create' => "Créer une correspondance de rubrique",
        'edit' => "Modifier la correspondance de rubrique",
        'empty' => "Aucune correspondance de rubrique — les rubriques par défaut des profils restent en vigueur.",
        'delivery' => "Transmission automatique",
        'delivery_help_text' => "Les exports terminés sont transmis automatiquement par profil, par e-mail et/ou SFTP, au bureau de paie ; la preuve (quand/où) est enregistrée sur l'export.",
        'delivery_edit' => "Configurer la transmission — :profile",
    ],

    'field' => [
        'basics' => "Correspondance",
        'profile' => "Profil d'export",
        'wage_type' => "Rubrique de paie interne",
        'wage_type_help' => "Rubriques standard de l'export des temps plus les types de majoration de votre organisation.",
        'external_code' => "Rubrique cible (externe)",
        'external_code_help' => "Numéro de rubrique dans le programme de paie cible — numérique jusqu'à 4 chiffres pour DATEV/Lexware.",
        'standard_types' => "Rubriques standard",
        'surcharge_types' => "Types de majoration (organisation)",
        'choose' => "– veuillez choisir –",
        'mail' => "Envoi par e-mail",
        'mail_toggle' => "Envoyer le fichier d'export par e-mail après finalisation",
        'mail_recipients' => "Destinataires",
        'mail_recipients_help' => "Séparer plusieurs adresses par une virgule, un point-virgule ou un retour à la ligne.",
        'sftp' => "Téléversement SFTP",
        'sftp_toggle' => "Téléverser le fichier d'export via SFTP après finalisation",
        'sftp_host' => "Hôte",
        'sftp_host_fingerprint' => 'Empreinte de la clé d’hôte',
        'sftp_port' => "Port",
        'sftp_username' => "Nom d'utilisateur",
        'sftp_password' => "Mot de passe",
        'sftp_password_help' => "Laisser vide pour conserver le mot de passe enregistré.",
        'sftp_root' => "Répertoire cible",
        'sftp_root_help' => "Vide = répertoire personnel de l'utilisateur SFTP.",
        'enabled' => "Actif",
        'disabled' => "Désactivé",
    ],

    'action' => [
        'create' => "Créer",
        'edit' => "Modifier",
        'save' => "Enregistrer",
        'delete' => "Supprimer",
        'delete_confirm' => "Supprimer vraiment cette correspondance de rubrique ? Les exports existants restent inchangés ; les futurs exports reviennent à la rubrique par défaut.",
        'configure' => "Configurer",
    ],

    'flash' => [
        'created' => "Correspondance de rubrique créée.",
        'updated' => "Correspondance de rubrique mise à jour.",
        'deleted' => "Correspondance de rubrique supprimée.",
        'delivery_saved' => "Configuration de transmission enregistrée.",
    ],

    'validation' => [
        'external_code_format' => "La rubrique cible n'a pas un format valide pour le profil d'export choisi (DATEV/Lexware : numérique, 1 à 4 chiffres).",
        'wage_type_unique' => "Une correspondance existe déjà pour cette rubrique dans ce profil.",
        'recipients_required' => "L'envoi par e-mail nécessite au moins une adresse de destinataire.",
        'password_required' => "Le téléversement SFTP nécessite un mot de passe.",
    ],

    'error' => [
        'sftp_fingerprint_missing' => 'L’empreinte de la clé d’hôte manque pour l’envoi SFTP. Sans elle, le fichier n’est pas transmis.',
        'missing_mappings' => "Export interrompu : les rubriques suivantes n'ont pas de rubrique cible dans le programme de paie : :types. Veuillez créer une correspondance sous « Rubriques de paie & transmission des exports » ou définir la rubrique sur la règle de majoration.",
    ],

    'delivery' => [
        'title_evidence' => "Transmission automatique",
        'evidence_mail' => "E-mail à :to",
        'evidence_sftp' => "SFTP vers :target",
        'note_auto' => "Transmis automatiquement (:channels).",
        'file_missing' => "Fichier d'export introuvable — transmission ignorée.",
        'abandoned' => "Transmission automatique définitivement échouée après plusieurs tentatives.",
    ],

    'mail' => [
        'subject' => "Export des temps :profile :period",
        'heading' => "Export des temps pour la paie",
        'body' => "Vous trouverez en pièce jointe l'export des temps du profil :profile pour la période :period.",
        'meta' => ":rows lignes · SHA-256 :hash",
    ],
    'help' => [
        'sftp_host_fingerprint' => 'Obligatoire : lie la connexion au bon serveur. À obtenir avec `ssh-keyscan -t rsa <host> | ssh-keygen -lf -`.',
    ],
];
