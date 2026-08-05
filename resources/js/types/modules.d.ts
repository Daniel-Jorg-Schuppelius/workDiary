// Ambiente Deklarationen für Nicht-JS-Importe (CSS-Side-Effects) und
// Subpfad-Importe ohne eigene Typdeklaration. Bewusst ohne import/export,
// damit die `declare module`-Einträge global ambient wirken.

declare module "*.css";

declare module "mind-elixir/style";
