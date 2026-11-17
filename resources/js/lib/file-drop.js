/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : file-drop.js
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/**
 * Reine Helfer für das Ablegen von Dateien (Feature 204) — ohne DOM, damit
 * sie unter node:test laufen.
 */

/**
 * Trägt der Ziehvorgang Dateien (und nicht etwa markierten Text)?
 *
 * @param {{ types?: ArrayLike<string> | null } | null | undefined} transfer
 * @returns {boolean}
 */
export function carriesFiles(transfer) {
    return Array.from(transfer?.types ?? []).includes("Files");
}

/**
 * Genau eine abgelegte Datei, sonst null (das Formular nimmt eine Datei je Absenden).
 *
 * @template T
 * @param {ArrayLike<T> | null | undefined} files
 * @returns {T | null}
 */
export function singleDroppedFile(files) {
    if (!files || files.length !== 1) {
        return null;
    }
    return files[0] ?? null;
}
