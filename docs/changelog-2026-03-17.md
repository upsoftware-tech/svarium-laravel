# Changelog - 2026-03-17

Ten wpis zbiera dzisiejsze zmiany wdrożone w obszarze formularzy zakładkowych (`Resource` / `FormTab`) i kart (`cards()`).

## Formularze tabowe (`FormTab`)

- Ujednolicono render kart tabów przez wspólny layout PHP:
  - `FormTabLayout` + `cards()` korzystają z tej samej klasy `FormTabCardLayout`.
- `FormTab` / `ResourceFormTab` obsługują pełny nagłówek karty:
  - `title`,
  - `subtitle`,
  - `icon`,
  - `action`.
- Dodano wsparcie `card(true|false)`:
  - `false` wyłącza wrapper karty dla konkretnej zakładki.
- Rozszerzono konfigurację tabów:
  - `tab.card`,
  - `tab.validation_error_icon.enabled`,
  - `tab.validation_error_icon.icon`.

## `cards()` w klasach `FormTabDefinition`

- Dodano sekcyjne budowanie kart przez `cards(...)` z nowym API:
  - `title`,
  - `subtitle` (alias `description`),
  - `icon`,
  - `action` (aliasy: `actions`, `headerComponents`, `header_components`),
  - `schema` (aliasy: `children`, `content`),
  - `card`,
  - `colSpan` (aliasy: `span`, `colspan`),
  - `cols` (wewnętrzny grid contentu karty),
  - `padding` (wewnętrzny padding body karty, domyślnie `4`).
- Dodano globalną siatkę kart na poziomie klasy taba:
  - `protected static int $grid = 1` (1..12),
  - `protected static int|string|float $gap = 4`.
- `colSpan` steruje szerokością karty w siatce kart, a `cols` steruje układem treści wewnątrz karty.

## Stabilizacja renderu dzieci komponentów

- Poprawiono normalizację children dla komponentów tabowych:
  - bezpieczne wsparcie zarówno `Component`, jak i node-array (`type/props/children`),
  - eliminacja błędu typu `TabItem::child(): ... array given`.
- Przywrócono działanie `Grid::cols(...)` / `colSpan(...)` w content tabów po refaktorze layoutu.

## `Card` (wariant `form-tab`)

- Rozszerzono komponent `Card`:
  - `variant('form-tab')`,
  - `icon(...)`,
  - `headerComponents(...)` / `action(...)`,
  - `contentPadding(...)`.
- Dla tabów dodano czytelne rozdzielenie API:
  - `widthContent(...)` dla `max-width`,
  - `paddingContent(...)` dla paddingu contentu taba.
- W `Card.vue` dla wariantu `form-tab` dodano obsługę dynamicznego paddingu contentu.
