# 365 | Events & Speaker – Changelog

## 3.2.0 – 2026-11-14

- Admin: Einheitliches Plugin-Layout von 365CMS 4.0.0 (PhinIT-Admin-Navigation 3.0). Kopfzeile mit Pluginname, Version, Einzeiler und den vier KPI-Karten rechts daneben; darunter links das Plugin-Menü (Übersicht, Events, Speaker, Kategorien & Tags, Einstellungen) mit aktivem Zustand und Entwürfe-Zähler bei „Events“, rechts der Seiteninhalt. Auf Mobilgeräten steht das Menü als umbrechende Leiste über dem Inhalt.
- Übersicht neu aufgebaut: Schnellaktionen (Neues Event, Neuer Speaker, Vergangene Events, öffentliche Eventseite), Status-/System-Check (Datenbanktabellen, kommende veröffentlichte Events, Entwürfe, öffentliche Speaker, Verknüpfung mit Experts & Companies, Google-Suche für den Speaker-Backfill) und die zuletzt bearbeiteten Events mit Status-Badge. Der doppelte Seitenkopf mit Pluginname und die Bereichs-Kacheln entfallen; die Tabelle „Nächste Events“ bleibt.

## 3.1.0 – 2026-11-14

- Admin: Einheitliches PhinIT-Admin-Layout 2.0 wie 365CMS 4.0.0: Der Seitenleisten-Link öffnet das Dashboard; oben auf allen Plugin-Seiten stehen der Pluginname mit den wichtigsten Kennzahlen (kommende Events, Events gesamt, Speaker, Entwürfe) und ein linksbündiges Menüband mit allen Bereichen (Dashboard, Events, Speaker, Kategorien & Tags, Einstellungen).
- Neues Dashboard unter `/admin/365netevents/dashboard` mit Kennzahlen-Karten, Bereichs-Kacheln und den nächsten fünf Events. Die Event-Liste bleibt unter `/admin/365netevents`; Formular-Ziele, Weiterleitungen und Lesezeichen gelten unverändert. Die doppelten Navigations-Buttons (Speaker, Kategorien & Tags, Einstellungen, Events) in den Seitenköpfen entfallen, das Menüband ersetzt sie.

## 3.0.16 – 2026-11-13

- Admin: Einheitliche PhinIT-Admin-Navigation. Das Plugin erscheint in der Seitenleiste als ein Eintrag ohne aufklappbare Unterpunkte; alle Bereiche sind als Reiter oben auf der Plugin-Seite erreichbar, alte Unterseiten-Adressen leiten weiter. Menü, Reiter und Überschriften ohne Emojis, Aktions-Symbole als Tabler-Icons wie im 365CMS-Core. Geprüft mit 365CMS 4.0.0.

## 3.0.15 – 2026-10-03

- Performance: Schema-Abgleich, Seeds und die automatische Verknüpfung aller Events/Speaker mit Experts & Companies liefen bei jedem Seitenaufruf (rund 1.300 Abfragen inkl. UPDATEs pro Request, auch auf fremden Seiten). ensureSchema() läuft jetzt nur noch, wenn die gespeicherte schema_version nicht zum Code passt; die Verknüpfung bleibt über „Verknüpfungen synchronisieren“ im Admin erreichbar.
- Barrierefreiheit (Mobil): Das Suchfeld im Archiv-Header wurde neben den Buttons auf 23 px Breite gedrückt (WCAG 2.5.8). Unter 640 px stehen Feld und Buttons jetzt untereinander.

## 3.0.14 – 2026-09-30

- Admin-Seitenleiste: Der Menüeintrag heißt jetzt „PHINIT | Events & Speaker“ (bisher „365 | Events & Speaker“), damit die PhinIT-Plugins im Abschnitt „Plugin-Erweiterungen“ zusammen stehen.
