// Ambiente Deklarationen für Nicht-JS-Importe (CSS-Side-Effects) und
// Subpfad-Importe ohne eigene Typdeklaration. Bewusst ohne import/export,
// damit die `declare module`-Einträge global ambient wirken.
// "*.css" deckt im Browser-Projekt auch vite/client ab; das Node-Projekt
// (Tests ziehen Module wie map.js nach) kennt nur diese Zeile.

declare module "*.css";

declare module "mind-elixir/style";
