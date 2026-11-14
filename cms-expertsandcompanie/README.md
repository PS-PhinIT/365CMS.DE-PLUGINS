# 365NET | Experts & Companie

Version: 1.1.0
Kombi-Plugin für eine gemeinsame Public-Übersicht von **Experts** und **Companies**.

## Features

- Gemeinsamer Public-Hub unter:
  - `/experts-companie`
  - `/experts-and-companie`
  - `/experts-companies`
  - `/experts`
  - `/companies`
- Eigener Admin-Einstieg: **PHINIT | Experts & Companie**
- Vollständig standalone: eigene Tabellen, eigene Seed-Daten, eigenes CRUD
- UI angelehnt an Events/Speakers:
  - **Experts** in Orange-Tönen
  - **Firmen** in Grün-Tönen

## Technische Struktur

- `cms-365NETexpertsandcompanie.php` – Bootstrap & Hook-Registrierung
- `includes/class-post-type.php` – Routen, Datenaggregation, Controller
- `includes/class-database.php` – Schema, Seed, CRUD
- `includes/class-admin.php` – Admin-Menü und Übersicht
- `includes/class-template-loader.php` – Template-Lookup inkl. Theme-Override
- `templates/archive-experts-companie.php` – Public-Ausgabe
- `templates/archive-experts.php` – Dedicated Experts Publicsite
- `templates/archive-companies.php` – Dedicated Companies Publicsite
- `assets/css/style.css` – Public-Styling
- `assets/css/admin.css` – Admin-Styling

## Hinweise

- Das Plugin nutzt ausschließlich eigene Tabellen: `365net_excomp_experts`, `365net_excomp_companies`, `365net_excomp_settings`.
- Seed-Daten kommen fest eingebettet aus `defaults/experts.csv` und `defaults/companies.csv`.
