# Zentrales Design-/Theme-Modul

> Stand 3.0.0 (2026-11-15) · Weitere Dokumente: [README.md](README.md) · [DATABASE.md](DATABASE.md) · [API.md](API.md)

Das globale Design-Modul trennt zentrale Theme-Werte von Landingpage-spezifischen Einstellungen. Landingpages entscheiden über `useGlobalDesign`, ob globale Werte genutzt werden oder stabile Default-/Page-Werte greifen.

## Config

Datei: `config/global-design.json`

```json
{
  "fontSizes": {
    "base": 16,
    "hero": 52,
    "sectionTitle": 32,
    "cardTitle": 21
  },
  "headerSpacing": 48,
  "footerSpacing": 48,
  "cardSpacing": 24,
  "sectionContentSpacing": 24
}
```

Im Admin entsprechen diese Werte den globalen Einstellungen `font_size_*`, `header_spacing`, `footer_spacing`, `card_spacing` und `section_content_spacing` (*Beratung › Globale Einstellungen*).

## Interfaces/Types

Datei: `src/theme/global-design.ts`

- `GlobalDesignConfig`: zentrale Theme-Konfiguration
- `LandingpageSettings`: Page-Konfiguration inklusive `useGlobalDesign`
- `LandingpageDesignOverrides`: optionale Page-Overrides, wenn globale Werte nicht genutzt werden
- `resolveLandingpageDesign(...)`: zentrale Fallback-Logik
- `toCssVariables(...)`: Mapping auf CSS Custom Properties

## Nutzung in einer Landingpage

```ts
import globalDesign from '../../config/global-design.json';
import { resolveLandingpageDesign, toCssVariables, type LandingpageSettings } from '../theme/global-design';

const pageSettings: LandingpageSettings = {
  slug: 'copilot-readiness-check',
  title: 'Copilot Readiness Check',
  useGlobalDesign: true,
};

const resolvedDesign = resolveLandingpageDesign(globalDesign, pageSettings);
const cssVariables = toCssVariables(resolvedDesign);
```

## Page-Override statt globalem Design

```ts
const pageSettings: LandingpageSettings = {
  slug: 'security-review',
  title: 'Microsoft 365 Security Review',
  useGlobalDesign: false,
  design: {
    fontSizes: {
      hero: 48,
      sectionTitle: 30
    },
    headerSpacing: 40,
    footerSpacing: 44,
    cardSpacing: 20
  }
};
```

## Fallback-Logik

1. `useGlobalDesign === true`: Werte aus `global-design.json` bzw. globalen Admin-Settings verwenden.
2. `useGlobalDesign === false`: stabile Modul-Defaults verwenden und optionale Page-Overrides darüberlegen.
3. Ungültige oder fehlende Werte werden über `normalizeGlobalDesign(...)` geklemmt.
4. PHP-Kompatibilität: Im bestehenden CMS-Datenmodell heißt der persistierte Schalter `use_global_design`; JSON-Import/Export akzeptiert zusätzlich `useGlobalDesign`.

## CSS-Ausgabe

Die Runtime mappt die Werte auf CSS-Variablen:

```css
--beratung-font-base
--beratung-font-hero
--beratung-font-section-title
--beratung-font-card-title
--beratung-header-spacing
--beratung-footer-spacing
--beratung-card-spacing
--beratung-section-content-spacing
```

Diese Variablen werden über `assets/css/frontend-global-design.css` nach den bestehenden Styles angewendet.

## Design-Presets (ab 3.0.0)

Farbwerte lassen sich als Preset speichern (*Beratung › Design-Presets*):

- **System-Presets** werden bei der Installation angelegt und sind schreibgeschützt.
- **Eigene Presets** speichern die zehn Farbfelder `primary_color` … `card_border_color` in `design_json`.
- **Übernehmen** schreibt die Preset-Farben in die globalen Design-Einstellungen; Landingpages mit `use_global_design = 1` übernehmen sie sofort.
- Im Editor einer Landingpage setzt die Preset-Auswahl die Werte nur für diese Seite (`custom_design_enabled = 1`, `use_global_settings = 0`).

## CSP-Hinweis

Die CSS-Variablen werden pro Seite als `style`-Attribute ausgegeben. Die 365CMS-4.0.0-CSP blockiert `style`-Attribute; der Core-`InlineStyleRewriter` überführt sie für vollständige HTML-Antworten in nonce-geschützte Klassen. Alle Werte sind serverseitig auf Farben bzw. Zahlen beschränkt.
