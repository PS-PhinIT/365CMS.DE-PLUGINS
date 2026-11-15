<?php
/**
 * CMS Beratung – Vorlagen (Landingpage-Vorschläge und Standardinhalt einer neuen Landingpage).
 *
 * @package CMS_Beratung
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class CMS_Beratung_Admin_Defaults
{
    /** @return array<string,array<string,string>> */
    public static function landingpage_proposals(): array
    {
        return [
            'microsoft-365-copilot' => ['icon' => 'ti ti-robot', 'title' => 'Microsoft 365 und Copilot Beratung', 'badge' => 'Vollständige Beispielseite', 'description' => 'Hero, Herausforderungen, Leistungen, GenAI vs. Agentic AI, Ablauf, Technologien, Trust, CTA und Kontakt. FAQs werden zentral gepflegt.'],
            'copilot-readiness' => ['icon' => 'ti ti-circle-check', 'title' => 'Copilot Readiness Check', 'badge' => 'Copilot Vorlage', 'description' => 'Für Tenant-Readiness, Berechtigungen, Datenstruktur, Governance und Pilotierung.'],
            'security-review' => ['icon' => 'ti ti-shield-check', 'title' => 'Microsoft 365 Security Review', 'badge' => 'Security Vorlage', 'description' => 'Für Entra ID, Conditional Access, Defender, MFA, Rollen und Maßnahmenplan.'],
            'governance-workshop' => ['icon' => 'ti ti-compass', 'title' => 'SharePoint Governance Workshop', 'badge' => 'Governance Vorlage', 'description' => 'Für SharePoint, OneDrive, Teams, Lifecycle, Berechtigungen und Namenskonzepte.'],
            'admin-automation' => ['icon' => 'ti ti-settings-automation', 'title' => 'Admin Workshop & PowerShell', 'badge' => 'Admin Vorlage', 'description' => 'Für Admin Enablement, Dokumentation, Automatisierung und Betriebsübergabe.'],
        ];
    }

    /** @return array<string,mixed> */
    public static function default_landingpage(string $proposal = 'microsoft-365-copilot'): array
    {
        $proposal = array_key_exists($proposal, self::landingpage_proposals()) ? $proposal : 'microsoft-365-copilot';
        $page = [
            'internal_title' => 'Microsoft 365 Beratung',
            'public_title' => 'Microsoft 365 und Copilot Beratung',
            'slug' => 'microsoft-365-und-copilot-beratung',
            'status' => 'draft',
            'template' => 'modern',
            'max_content_width' => 1160,
            'use_global_settings' => 1,
            'use_global_design' => 1,
            'show_header' => 1,
            'show_footer' => 1,
            'show_breadcrumb' => 1,
            'show_toc' => 1,
            'show_anchor_nav' => 1,
            'hero' => [
                'enabled' => true,
                'image_url' => '',
                'image_position' => 'left',
                'image_flush' => true,
                'image_height' => 520,
                'image_width' => 46,
                'image_fit' => 'cover',
                'image_alt' => 'Microsoft 365 Beratung',
                'badge_text' => 'Microsoft 365 und Copilot Beratung',
                'badge_show' => true,
                'standalone_header_enabled' => false,
                'standalone_header_slug' => 'microsoft-365-und-copilot-beratung-standalone',
                'standalone_header_blog_logo_url' => '',
                'standalone_header_partner_logo_url' => '',
                'standalone_header_title' => 'Microsoft 365 und Copilot Beratung',
                'standalone_header_subtitle' => 'Standalone Publicsite ohne Theme Header und Footer.',
                'standalone_header_menu_enabled' => true,
                'standalone_header_menu_items' => [
                    ['label' => 'Leistungen', 'target' => '#leistungen'],
                    ['label' => 'Ablauf', 'target' => '#ablauf'],
                    ['label' => 'Termin', 'target' => '#termin-buchen'],
                    ['label' => 'Kontakt', 'target' => '#kontakt'],
                ],
                'title' => 'Microsoft 365 und Copilot sauber einführen, statt einfach nur Lizenzen zu verteilen',
                'subtitle' => 'Microsoft 365, Copilot, Security und Governance technisch sauber bewerten und praxisnah umsetzen.',
                'description' => 'Ich unterstütze Dich dabei, Microsoft 365, Copilot, Security und Governance technisch sauber zu bewerten, sinnvoll zu strukturieren und praxisnah umzusetzen.',
                'button_1' => ['text' => 'Beratung anfragen', 'target' => '#kontakt', 'target_type' => 'contact', 'style' => 'primary'],
                'button_2' => ['text' => 'Leistungen ansehen', 'target' => '#leistungen', 'target_type' => 'anchor', 'style' => 'ghost'],
                'button_3' => ['text' => 'Copilot Readiness prüfen', 'target' => '#copilot-readiness', 'target_type' => 'anchor', 'style' => 'secondary'],
                'trust_badges' => ['Ex-Microsoft MVP', '20+ Jahre', 'LPIC 1 & 2', 'Microsoft zertifiziert'],
                'trust_image_enabled' => true,
                'trust_image_url' => '',
                'trust_image_alt' => 'Portrait eines Microsoft 365 Beraters',
                'partner_band_enabled' => false,
                'partner_band_text' => 'Zugehörig zum copilotberater.de Netzwerk',
                'partner_band_website_label' => 'copilotberater.de',
                'partner_band_website_url' => 'https://copilotberater.de',
                'partner_band_map_label' => 'Copilotberater Deutschland Karte',
                'partner_band_map_url' => 'https://copilotberater.de/copilotberater-deutschland-karte/',
                'anchor_nav_layout' => 'pills',
                'toc_right_display' => 'card',
                'toc_right_layout' => 'card',
                'trust_text' => 'Praxisnahe Beratung für Microsoft 365, Copilot, Security und Compliance.',
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'vertical_align' => 'center',
                'mobile_order' => 'image-first',
            ],
            'sections' => [[
                'id' => 'intro',
                'anchor_id' => 'intro',
                'enabled' => true,
                'type' => 'text',
                'internal_name' => 'Intro Bereich',
                'eyebrow' => 'Einordnung',
                'title' => 'Microsoft 365 Beratung beginnt bei Struktur, Sicherheit und Alltagstauglichkeit',
                'intro' => 'Bevor neue Tools wie Copilot echten Mehrwert liefern, müssen Berechtigungen, Datenqualität, Governance und Betriebsprozesse zusammenpassen. Diese Beispielseite zeigt eine vollständige Beratungs-Landingpage mit austauschbaren Platzhalterinhalten.',
                'columns' => 1,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'cards' => [],
            ], [
                'id' => 'herausforderungen',
                'anchor_id' => 'herausforderungen',
                'enabled' => true,
                'type' => 'card_grid',
                'internal_name' => 'Typische Herausforderungen',
                'eyebrow' => 'Ausgangslage',
                'title' => 'Typische Herausforderungen vor Microsoft 365 und Copilot Projekten',
                'intro' => 'Diese Punkte tauchen in gewachsenen Microsoft 365 Umgebungen besonders häufig auf.',
                'columns' => 3,
                'card_type' => 'text',
                'card_design' => 'accent',
                'equal_height' => true,
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔓', 'title' => 'Zu viele Berechtigungen', 'text' => 'Zu viele Berechtigungen in SharePoint und OneDrive machen Datenzugriffe schwer nachvollziehbar.'],
                    ['enabled' => true, 'icon' => '💬', 'title' => 'Unklare Teams Governance', 'text' => 'Microsoft Teams ist produktiv, aber Namenskonzepte, Lebenszyklen und Verantwortlichkeiten sind unklar.'],
                    ['enabled' => true, 'icon' => 'ti ti-robot', 'title' => 'Copilot ohne Vorbereitung', 'text' => 'Copilot wird eingeführt, aber der Tenant ist technisch und organisatorisch noch nicht vorbereitet.'],
                    ['enabled' => true, 'icon' => '🚦', 'title' => 'Historischer Conditional Access', 'text' => 'Conditional Access und MFA sind historisch gewachsen und enthalten Ausnahmen oder blinde Flecken.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Purview nicht sauber konfiguriert', 'text' => 'DLP, Sensitivity Labels und Aufbewahrung sind vorhanden, aber nicht konsistent nutzbar.'],
                ],
            ], [
                'id' => 'leistungen',
                'anchor_id' => 'leistungen',
                'enabled' => true,
                'type' => 'services',
                'internal_name' => 'Meine Leistungen',
                'eyebrow' => 'Meine Leistungen',
                'title' => 'Microsoft 365 und Copilot Beratung aus der Praxis',
                'intro' => 'Frei sortierbare Leistungen für Tenant, Security, Compliance, Governance, Automatisierung und Workshops.',
                'columns' => 4,
                'card_type' => 'text',
                'card_design' => 'accent',
                'equal_height' => true,
                'categories_enabled' => true,
                'note_text' => 'Alle Leistungen können als Einzeltermin, Workshop oder Projektbegleitung kombiniert werden.',
                'button_1_text' => 'Beratung anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'button_1_style' => 'primary',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'padding_top' => 56,
                'padding_bottom' => 56,
                'max_width' => 1160,
                'text_align' => 'left',
                'cards' => [
                    ['enabled' => true, 'icon' => '🏢', 'category' => 'Tenant', 'title' => 'Microsoft 365 Tenant Check', 'text' => 'Du bekommst eine klare Risiko- und Lizenzeinschätzung deines Tenants und weißt, wo Governance fehlt.'],
                    ['enabled' => true, 'icon' => 'ti ti-robot', 'category' => 'Copilot', 'title' => 'Copilot Readiness Check', 'text' => 'Du erkennst, ob Datenzugriffe, Inhalte und Compliance für Copilot belastbar vorbereitet sind. So startest du kontrolliert statt mit blinden Flecken.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Identity', 'title' => 'Entra ID Security Review', 'text' => 'Du siehst, welche Identitätsrisiken deinen Tenant wirklich betreffen. Daraus entstehen konkrete Schritte für MFA, Rollen und Zugriffsschutz.'],
                    ['enabled' => true, 'icon' => '📜', 'category' => 'Compliance', 'title' => 'Microsoft Purview Beratung', 'text' => 'Du weißt, welche Purview-Funktionen für deine Daten sinnvoll sind und wie Schutz, DLP und Aufbewahrung zusammenwirken.'],
                    ['enabled' => true, 'icon' => 'ti ti-compass', 'category' => 'Governance', 'title' => 'SharePoint und OneDrive Governance', 'text' => 'Du bekommst klare Regeln für Sites, Freigaben und Berechtigungen. So reduzierst du Wildwuchs und schaffst eine bessere Grundlage für Copilot.'],
                    ['enabled' => true, 'icon' => '✉️', 'category' => 'Exchange', 'title' => 'Exchange Online Analyse', 'text' => 'Du erkennst Risiken in Mailfluss, Postfächern und Schutzfunktionen. Danach weißt du, welche Anpassungen Betrieb und Sicherheit verbessern.'],
                    ['enabled' => true, 'icon' => 'ti ti-shield-check', 'category' => 'Security', 'title' => 'Microsoft Defender Review', 'text' => 'Du bekommst priorisierte Findings zu Defender und Secure Score. So weißt du, welche Schutzmaßnahmen zuerst Wirkung bringen.'],
                    ['enabled' => true, 'icon' => '🚦', 'category' => 'Access', 'title' => 'Conditional Access Bewertung', 'text' => 'Du erkennst unsichere Ausnahmen, Lücken und Konflikte in deinen Richtlinien. Daraus entsteht ein belastbarer Zugriffsschutz für Benutzer und Admins.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Enablement', 'title' => 'Admin Workshops', 'text' => 'Dein Admin-Team versteht die Entscheidungen und kann sie selbstständig weiterführen. Die Inhalte richten sich an euren echten Aufgaben aus.'],
                    ['enabled' => true, 'icon' => 'ti ti-settings-automation', 'category' => 'Automation', 'title' => 'PowerShell Automatisierung', 'text' => 'Du reduzierst wiederkehrende Admin-Arbeit und bekommst nachvollziehbare Skripte für stabile Abläufe.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Betrieb', 'title' => 'Dokumentation und Übergabe', 'text' => 'Du erhältst verständliche Ergebnisse, Entscheidungen und nächste Schritte. Damit bleiben Wissen und Verantwortung im Team nutzbar.'],
                    ['enabled' => true, 'icon' => '🤝', 'category' => 'Projekt', 'title' => 'Projektbegleitung', 'text' => 'Du bekommst technische Begleitung während Umsetzung, Review und Übergabe. So bleiben Entscheidungen sauber und Risiken früh sichtbar.'],
                ],
            ], [
                'id' => 'zusammenarbeit',
                'anchor_id' => 'zusammenarbeit',
                'enabled' => true,
                'type' => 'collaboration',
                'internal_name' => 'Zusammenarbeit',
                'eyebrow' => 'Zusammenarbeit',
                'title' => 'Expertinnen und Experten, mit denen ich bei dieser Dienstleistung zusammenarbeite',
                'intro' => 'Für Spezialthemen kann die Dienstleistung durch ausgewählte Experts aus dem 365 Network ergänzt werden.',
                'columns' => 3,
                'card_design' => 'accent',
                'expert_ids' => [],
                'mvp_note_enabled' => true,
                'mvp_note_text' => 'Darunter auch Microsoft MVPs aus dem 365 Network.',
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'padding_top' => 56,
                'padding_bottom' => 56,
                'max_width' => 1160,
                'text_align' => 'left',
            ], [
                'id' => 'ablauf',
                'anchor_id' => 'ablauf',
                'enabled' => true,
                'type' => 'steps',
                'internal_name' => 'Beratungsablauf',
                'eyebrow' => 'Vorgehen',
                'title' => 'So läuft die Zusammenarbeit ab',
                'intro' => 'Ein klarer Ablauf macht Beratung planbar, nachvollziehbar und technisch belastbar.',
                'columns' => 4,
                'card_type' => 'step',
                'display_style' => 'horizontal',
                'auto_number' => true,
                'connector' => true,
                'equal_height' => true,
                'button_1_text' => 'Erstgespräch anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'background_color' => '#f8fafc',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Erstgespräch', 'text' => 'Rahmen, Ausgangslage und gewünschtes Ergebnis klären.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Zielklärung', 'text' => 'Prioritäten, Risiken und betroffene Microsoft 365 Bereiche festlegen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Technische Analyse', 'text' => 'Tenant, Entra ID, Security, Compliance und Governance prüfen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Bewertung', 'text' => 'Findings einordnen und realistische Handlungsoptionen ableiten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Maßnahmenplan', 'text' => 'Konkrete Roadmap mit Quick Wins und sauberer Priorisierung erstellen.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Umsetzung', 'text' => 'Konfigurationen, Pilotierung und Admin Enablement begleiten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Dokumentation', 'text' => 'Ergebnisse, Entscheidungen und Betriebswissen verständlich festhalten.', 'connector' => true],
                    ['enabled' => true, 'card_type' => 'step', 'auto_number' => true, 'title' => 'Übergabe', 'text' => 'Nächste Schritte, Betrieb und Verantwortlichkeiten sauber übergeben.', 'connector' => false],
                ],
            ], [
                'id' => 'vergleich-genai-agentic-ai',
                'anchor_id' => 'vergleich',
                'enabled' => true,
                'type' => 'comparison',
                'internal_name' => 'Vergleich GenAI Agentic AI',
                'eyebrow' => 'Einordnung',
                'title' => 'Vergleiche und Infografik Inhalte',
                'intro' => 'Komplexe KI- und Governance-Themen verständlich gegenüberstellen.',
                'columns' => 2,
                'comparison_variant' => 'genai_agentic',
                'title_band_enabled' => true,
                'title_band_text' => 'GenAI vs. Agentic AI: Die wichtigsten Unterschiede',
                'title_band_background_color' => '#1e3a8a',
                'title_band_text_color' => '#ffffff',
                'border_enabled' => true,
                'shadow_enabled' => true,
                'mobile_stack' => true,
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'cards' => [
                    ['enabled' => true, 'icon' => '✨', 'title' => 'Generative KI', 'text' => 'Erstellt Inhalte wie Texte, Bilder oder Code auf Basis großer Sprachmodelle. Kreativ und leistungsfähig, aber oft isoliert und ohne direkten Bezug zu Unternehmensprozessen.', 'extra_text' => 'Typischer Einsatz: Content Erstellung, Ideenfindung, Automatisierung einfacher Aufgaben'],
                    ['enabled' => true, 'icon' => '🧠', 'title' => 'Agentic AI', 'text' => 'Geht einen Schritt weiter, handelt proaktiv, integriert sich in bestehende Systeme und führt Aufgaben je nach Anforderung teilautonom aus.', 'extra_text' => 'Typischer Einsatz: Prozessautomatisierung, intelligente Assistenz, datenbasierte Entscheidungsunterstützung'],
                ],
            ], [
                'id' => 'faq',
                'anchor_id' => 'faq',
                'enabled' => true,
                'type' => 'faq',
                'internal_name' => 'FAQ Modul',
                'eyebrow' => 'FAQ',
                'title' => 'Häufige Fragen zur Microsoft 365 und Copilot Beratung',
                'intro' => 'Antworten auf typische Fragen vor einem Beratungsprojekt.',
                'faq_allow_multiple' => false,
                'faq_icon_style' => 'plus',
                'faq_open_behavior' => 'first',
                'faq_schema_enabled' => true,
                'faq_question_background_color' => '#ffffff',
                'faq_question_text_color' => '#111827',
                'faq_answer_background_color' => '#f8fafc',
                'background_color' => '#f8fafc',
                'cards' => [
                    ['enabled' => true, 'question' => 'Was ist Microsoft 365 Copilot?', 'answer' => 'Microsoft 365 Copilot verbindet KI-Funktionen mit Microsoft 365 Apps und Unternehmensdaten, sofern Berechtigungen, Datenstruktur und Compliance sauber vorbereitet sind.', 'default_open' => true],
                    ['enabled' => true, 'question' => 'Für wen eignet sich Microsoft Copilot?', 'answer' => 'Copilot eignet sich für Organisationen, die Microsoft 365 aktiv nutzen und ihre Daten, Berechtigungen und Prozesse transparent im Griff haben möchten.'],
                    ['enabled' => true, 'question' => 'Ist Microsoft Copilot DSGVO konform einsetzbar?', 'answer' => 'Ein DSGVO-konformer Einsatz hängt von Konfiguration, Datenklassifizierung, Governance, Betriebsprozessen und rechtlicher Bewertung ab. Die Beratung hilft bei der technischen Einordnung.'],
                    ['enabled' => true, 'question' => 'Welche Voraussetzungen braucht mein Tenant?', 'answer' => 'Wichtig sind saubere Identitäten, Berechtigungen, Informationsschutz, SharePoint Governance, Auditierbarkeit und passende Lizenzierung.'],
                    ['enabled' => true, 'question' => 'Wie läuft ein Copilot Readiness Check ab?', 'answer' => 'Der Check bewertet Tenant-Konfiguration, Datenzugriffe, Security, Compliance, Governance und organisatorische Bereitschaft.'],
                    ['enabled' => true, 'question' => 'Was kostet eine Microsoft 365 Beratung?', 'answer' => 'Das hängt von Umfang, Zielen und gewünschter Tiefe ab. Nach einem Erstgespräch kann der Aufwand realistisch eingegrenzt werden.'],
                    ['enabled' => true, 'question' => 'Kann die Beratung remote stattfinden?', 'answer' => 'Ja, viele Checks, Workshops und Reviews können remote durchgeführt werden.'],
                    ['enabled' => true, 'question' => 'Unterstützt du auch bei der technischen Umsetzung?', 'answer' => 'Ja, neben Analyse und Konzept kann auch die technische Umsetzung begleitet werden.'],
                    ['enabled' => true, 'question' => 'Gibt es eine Dokumentation nach der Beratung?', 'answer' => 'Ja, Ergebnisse, Empfehlungen und nächste Schritte werden verständlich dokumentiert.'],
                    ['enabled' => true, 'question' => 'Können Workshops für Admin Teams durchgeführt werden?', 'answer' => 'Ja, Workshops für Admin Teams sind ein zentraler Bestandteil des Angebots.'],
                ],
            ], [
                'id' => 'trust',
                'anchor_id' => 'vertrauen',
                'enabled' => true,
                'type' => 'trust',
                'internal_name' => 'Trust Bereich',
                'eyebrow' => 'Vertrauen',
                'title' => 'Technische Beratung statt reines Folien Consulting',
                'intro' => 'Praxis, Betrieb und Sicherheit stehen im Mittelpunkt.',
                'columns' => 3,
                'button_1_text' => 'Projekt besprechen',
                'button_1_target' => '#kontakt',
                'background_color' => '#ffffff',
                'cards' => [
                    ['enabled' => true, 'icon' => '⏱️', 'metric' => '20+ Jahre', 'title' => 'IT Erfahrung', 'text' => 'Langjährige Praxis in Infrastruktur, Microsoft 365 und Administration.'],
                    ['enabled' => true, 'icon' => '🏢', 'title' => 'Echte Admin Umgebungen', 'text' => 'Microsoft 365 Praxis aus produktiven Szenarien statt Labortheorie.'],
                    ['enabled' => true, 'icon' => 'ti ti-shield-check', 'title' => 'Security und Governance', 'text' => 'Fokus auf Sicherheit, Governance und nachhaltigen Betrieb.'],
                    ['enabled' => true, 'icon' => '🛠️', 'title' => 'Technische Beratung', 'text' => 'Konkrete Konfiguration, Bewertung und Umsetzung statt Folienfriedhof.'],
                    ['enabled' => true, 'icon' => '📘', 'title' => 'Verständliche Dokumentation', 'text' => 'Ergebnisse so dokumentiert, dass Teams damit weiterarbeiten können.'],
                    ['enabled' => true, 'icon' => '🎓', 'title' => 'Admin Workshops', 'text' => 'Praxisnahe Workshops für Admin Teams und Betrieb.'],
                ],
            ], [
                'id' => 'technologien',
                'anchor_id' => 'technologien',
                'enabled' => true,
                'type' => 'technology',
                'internal_name' => 'Technologie Bereich',
                'eyebrow' => 'Technologien',
                'title' => 'Relevante Microsoft Technologien',
                'intro' => 'Beratung entlang der Plattformen, die im Microsoft 365 Betrieb wirklich zusammenspielen.',
                'columns' => 4,
                'display_style' => 'icon_grid',
                'background_color' => '#f8fafc',
                'cards' => [
                    ['enabled' => true, 'icon' => '☁️', 'name' => 'Microsoft 365'], ['enabled' => true, 'icon' => 'ti ti-robot', 'name' => 'Microsoft Copilot'], ['enabled' => true, 'icon' => '🔐', 'name' => 'Microsoft Entra ID'], ['enabled' => true, 'icon' => '📜', 'name' => 'Microsoft Purview'],
                    ['enabled' => true, 'icon' => 'ti ti-shield-check', 'name' => 'Microsoft Defender'], ['enabled' => true, 'icon' => 'ti ti-compass', 'name' => 'SharePoint Online'], ['enabled' => true, 'icon' => '📁', 'name' => 'OneDrive for Business'], ['enabled' => true, 'icon' => '✉️', 'name' => 'Exchange Online'],
                    ['enabled' => true, 'icon' => '💬', 'name' => 'Microsoft Teams'], ['enabled' => true, 'icon' => 'ti ti-settings-automation', 'name' => 'PowerShell'], ['enabled' => true, 'icon' => '🌐', 'name' => 'Azure'], ['enabled' => true, 'icon' => '🚦', 'name' => 'Conditional Access'],
                ],
            ], [
                'id' => 'cta-copilot-readiness',
                'anchor_id' => 'copilot-readiness',
                'enabled' => true,
                'type' => 'cta',
                'internal_name' => 'CTA Band Copilot Readiness',
                'eyebrow' => 'Copilot Readiness',
                'title' => 'Du möchtest wissen, ob dein Microsoft 365 Tenant bereit für Copilot ist?',
                'intro' => 'Dann lass uns gemeinsam prüfen, wo Berechtigungen, Datenstruktur, Governance und Compliance wirklich stehen.',
                'display_style' => 'compact',
                'button_1_text' => 'Beratung anfragen',
                'button_1_target' => '#kontakt',
                'button_1_target_type' => 'contact',
                'button_1_style' => 'primary',
                'button_2_text' => 'Leistungen ansehen',
                'button_2_target' => '#leistungen',
                'button_2_target_type' => 'anchor',
                'button_2_style' => 'ghost',
                'background_color' => '#1e3a8a',
                'text_color' => '#ffffff',
            ], [
                'id' => 'trenner',
                'anchor_id' => 'trenner',
                'enabled' => true,
                'type' => 'divider',
                'internal_name' => 'Zitat Trenner',
                'divider_type' => 'quote',
                'divider_title' => 'Gute Copilot Einführung beginnt nicht beim Prompt, sondern bei Daten, Identitäten und Governance.',
                'divider_icon' => '💬',
                'background_color' => '#eff6ff',
                'text_color' => '#111827',
                'divider_line_color' => '#93c5fd',
                'divider_width' => 80,
                'divider_padding_left' => 20,
                'divider_padding_right' => 20,
            ], [
                'id' => 'html-hinweis',
                'anchor_id' => 'html-hinweis',
                'enabled' => false,
                'type' => 'html',
                'internal_name' => 'Freier HTML Bereich',
                'eyebrow' => 'Optional',
                'title' => 'Freier HTML Bereich',
                'intro' => 'Nur für berechtigte Benutzer. Unsichere Skripte werden gefiltert.',
                'html' => '<p><strong>Optionaler Hinweis:</strong> Dieser Bereich kann für geprüfte HTML-Inhalte verwendet werden.</p>',
            ]],
            'design' => [],
        ];

        $updateSection = static function (array &$landingpage, string $id, array $updates): void {
            foreach ($landingpage['sections'] as &$section) {
                if (($section['id'] ?? '') !== $id) {
                    continue;
                }
                foreach ($updates as $key => $value) {
                    $section[$key] = $value;
                }
                break;
            }
            unset($section);
        };

        if ($proposal === 'copilot-readiness') {
            $page['internal_title'] = 'Copilot Readiness Check';
            $page['public_title'] = 'Copilot Readiness Check';
            $page['slug'] = 'copilot-readiness-check';
            $page['hero']['badge_text'] = 'Copilot Readiness';
            $page['hero']['title'] = 'Microsoft 365 Copilot vorbereiten, bevor sensible Daten sichtbar werden';
            $page['hero']['subtitle'] = 'Tenant, Berechtigungen, Governance und Compliance als Grundlage für Copilot.';
            $page['hero']['description'] = 'Diese Vorlage zeigt eine Landingpage für Copilot Readiness Checks mit Platzhaltertexten zu Datenzugriffen, SharePoint, Purview, Entra ID und Pilotierung.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Risiken vor einer Copilot Einführung',
                'intro' => 'Copilot verstärkt vorhandene Daten- und Berechtigungsstrukturen. Genau deshalb lohnt sich der Readiness Check vor dem Rollout.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔎', 'title' => 'Unklare Datenzugriffe', 'text' => 'Zu viele Personen können sensible SharePoint- oder OneDrive-Inhalte finden.'],
                    ['enabled' => true, 'icon' => '📚', 'title' => 'Veraltete Inhalte', 'text' => 'Alte Dokumente, Dubletten und Testdaten verschlechtern Copilot-Antworten.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Fehlende Klassifizierung', 'text' => 'Sensitivity Labels, DLP und Aufbewahrung sind nicht konsequent umgesetzt.'],
                    ['enabled' => true, 'icon' => '👥', 'title' => 'Pilotgruppe unklar', 'text' => 'Ohne sinnvolle Pilotgruppen entstehen falsche Erwartungen und wenig messbarer Nutzen.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Copilot Readiness Bausteine',
                'intro' => 'Konkrete Prüfpunkte für einen kontrollierten Microsoft 365 Copilot Start.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => 'ti ti-compass', 'category' => 'Readiness', 'title' => 'Tenant Readiness', 'text' => 'Lizenzierung, Admin Center, Basiskonfiguration und technische Voraussetzungen prüfen.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Datenzugriff', 'title' => 'Permission Review', 'text' => 'SharePoint, OneDrive und Teams Berechtigungen risikoorientiert bewerten.'],
                    ['enabled' => true, 'icon' => '🏷️', 'category' => 'Compliance', 'title' => 'Purview Quick Check', 'text' => 'Labels, DLP, Audit und Aufbewahrung als Copilot-Schutzschicht einordnen.'],
                    ['enabled' => true, 'icon' => '🧪', 'category' => 'Pilot', 'title' => 'Pilotkonzept', 'text' => 'Pilotgruppen, Use Cases, Erfolgskriterien und Feedbackprozess definieren.'],
                    ['enabled' => true, 'icon' => '📈', 'category' => 'Adoption', 'title' => 'Enablement Plan', 'text' => 'Admin- und Anwenderkommunikation für realistische Copilot-Nutzung vorbereiten.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Ergebnis', 'title' => 'Readiness Report', 'text' => 'Priorisierte Maßnahmen, Quick Wins und konkrete nächste Schritte dokumentieren.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Copilot Readiness Check', 'cards' => [
                ['enabled' => true, 'question' => 'Warum vor Copilot einen Readiness Check durchführen?', 'answer' => 'Weil Copilot vorhandene Berechtigungen und Datenqualität nutzt. Schwächen werden dadurch sichtbarer.'],
                ['enabled' => true, 'question' => 'Wird Copilot dabei bereits aktiviert?', 'answer' => 'Nein, der Check bewertet die Voraussetzungen und bereitet einen kontrollierten Pilot vor.'],
                ['enabled' => true, 'question' => 'Welche Bereiche werden geprüft?', 'answer' => 'SharePoint, OneDrive, Teams, Entra ID, Purview, Lizenzierung, Governance und Pilotfähigkeit.'],
            ]]);
        } elseif ($proposal === 'security-review') {
            $page['internal_title'] = 'Microsoft 365 Security Review';
            $page['public_title'] = 'Microsoft 365 Security Review';
            $page['slug'] = 'microsoft-365-security-review';
            $page['template'] = 'security';
            $page['hero']['badge_text'] = 'Security · Entra ID · Defender';
            $page['hero']['title'] = 'Microsoft 365 Security sichtbar machen und Risiken priorisieren';
            $page['hero']['subtitle'] = 'Entra ID, Conditional Access, MFA, Defender und Adminrollen strukturiert prüfen.';
            $page['hero']['description'] = 'Diese Vorlage ist für Security Reviews mit Platzhaltertexten zu Identitäten, Zugriffen, Schutzfunktionen und Maßnahmenplan vorbereitet.';
            $page['hero']['background_color'] = '#0f172a';
            $page['hero']['text_color'] = '#ffffff';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Security-Schwachstellen in Microsoft 365',
                'intro' => 'Diese Vorlage fokussiert Identitäten, Adminrollen, Zugriffe und Schutzfunktionen.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🔑', 'title' => 'Zu viele Adminrollen', 'text' => 'Privilegierte Konten sind nicht sauber getrennt, dokumentiert oder überwacht.'],
                    ['enabled' => true, 'icon' => '🚪', 'title' => 'Conditional Access Lücken', 'text' => 'Ausnahmen, Legacy Authentication oder fehlende Gerätesignale erzeugen Risiko.'],
                    ['enabled' => true, 'icon' => 'ti ti-shield-check', 'title' => 'Defender nicht ausgeschöpft', 'text' => 'Schutzfunktionen sind vorhanden, aber nicht konsistent aktiviert oder überwacht.'],
                    ['enabled' => true, 'icon' => '👤', 'title' => 'Gastzugriffe unklar', 'text' => 'Externe Benutzer, Freigaben und Kollaboration sind nicht transparent.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Security Review Module',
                'intro' => 'Gezielte Analyse für Entra ID, Conditional Access, Defender und Admin-Betrieb.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🪪', 'category' => 'Identity', 'title' => 'Entra ID Rollencheck', 'text' => 'Adminrollen, PIM-Vorbereitung, Break-Glass und privilegierte Konten bewerten.'],
                    ['enabled' => true, 'icon' => '🚦', 'category' => 'Access', 'title' => 'Conditional Access Review', 'text' => 'Richtlinien, Ausnahmen, MFA, Geräte und Risiko-Signale prüfen.'],
                    ['enabled' => true, 'icon' => 'ti ti-shield-check', 'category' => 'Defender', 'title' => 'Defender Konfigurationscheck', 'text' => 'E-Mail, Endpoint, Identity und Cloud Apps Schutzfunktionen einordnen.'],
                    ['enabled' => true, 'icon' => '📊', 'category' => 'Secure Score', 'title' => 'Priorisierung', 'text' => 'Findings nach Risiko, Aufwand und Wirkung sortieren.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Dokumentation', 'title' => 'Maßnahmenplan', 'text' => 'Konkreter Maßnahmenplan mit Quick Wins und Verantwortlichkeiten.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Enablement', 'title' => 'Admin Briefing', 'text' => 'Ergebnisse verständlich erklären und Betriebsteam befähigen.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Microsoft 365 Security Review', 'cards' => [
                ['enabled' => true, 'question' => 'Ist der Review invasiv?', 'answer' => 'Nein, im Fokus stehen Konfiguration, Export/Ansicht vorhandener Einstellungen und nachvollziehbare Bewertung.'],
                ['enabled' => true, 'question' => 'Wer sollte teilnehmen?', 'answer' => 'Mindestens Microsoft 365 Admins, Security-Verantwortliche und Personen mit Entscheidungsbefugnis.'],
                ['enabled' => true, 'question' => 'Gibt es konkrete Empfehlungen?', 'answer' => 'Ja, die Ergebnisse werden priorisiert und mit Quick Wins sowie Folgeaufgaben dokumentiert.'],
            ]]);
        } elseif ($proposal === 'governance-workshop') {
            $page['internal_title'] = 'SharePoint Governance Workshop';
            $page['public_title'] = 'SharePoint und OneDrive Governance Workshop';
            $page['slug'] = 'sharepoint-governance-workshop';
            $page['hero']['badge_text'] = 'SharePoint · OneDrive · Teams';
            $page['hero']['title'] = 'SharePoint, OneDrive und Teams sauber strukturieren';
            $page['hero']['subtitle'] = 'Governance, Berechtigungen, Lifecycle und Namenskonzepte verständlich aufbauen.';
            $page['hero']['description'] = 'Diese Vorlage ist für Governance Workshops mit Platzhaltertexten zu Freigaben, Sites, Teams, Lifecycle und Rollenmodell vorbereitet.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Governance-Probleme in SharePoint und Teams',
                'intro' => 'Diese Vorlage richtet sich an Organisationen mit gewachsenen Sites, Teams und Freigaben.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🗂️', 'title' => 'Unklare Site-Struktur', 'text' => 'Sites, Hubs und Teams sind historisch gewachsen und schwer nachvollziehbar.'],
                    ['enabled' => true, 'icon' => '🔗', 'title' => 'Freigaben ohne Leitplanken', 'text' => 'Externe Links und Gastzugriffe werden nicht einheitlich gesteuert.'],
                    ['enabled' => true, 'icon' => '♻️', 'title' => 'Kein Lifecycle', 'text' => 'Alte Teams und Sites bleiben bestehen, obwohl sie nicht mehr genutzt werden.'],
                    ['enabled' => true, 'icon' => '🏷️', 'title' => 'Fehlende Namenskonzepte', 'text' => 'Teams, Gruppen und Sites folgen keinem verständlichen Muster.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Governance Workshop Inhalte',
                'intro' => 'Praktische Bausteine für SharePoint, OneDrive und Teams Governance.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => 'ti ti-compass', 'category' => 'Struktur', 'title' => 'Informationsarchitektur', 'text' => 'Sites, Hubs, Teams und Ablagebereiche verständlich strukturieren.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Zugriff', 'title' => 'Berechtigungsmodell', 'text' => 'Rollen, Freigaben, Gäste und Verantwortlichkeiten sauber festlegen.'],
                    ['enabled' => true, 'icon' => '♻️', 'category' => 'Lifecycle', 'title' => 'Lebenszyklus-Konzept', 'text' => 'Erstellung, Prüfung, Archivierung und Löschung von Arbeitsbereichen definieren.'],
                    ['enabled' => true, 'icon' => '🏷️', 'category' => 'Naming', 'title' => 'Namenskonventionen', 'text' => 'Benennung, Vorlagen und Metadaten praxisnah festlegen.'],
                    ['enabled' => true, 'icon' => '📜', 'category' => 'Regeln', 'title' => 'Governance Leitfaden', 'text' => 'Kurze, verständliche Regeln statt unlesbarer Richtliniendokumente.'],
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Workshop', 'title' => 'Admin Enablement', 'text' => 'Admins und Site Owner für den Alltag befähigen.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zum Governance Workshop', 'cards' => [
                ['enabled' => true, 'question' => 'Geht es nur um SharePoint?', 'answer' => 'Nein, betrachtet werden SharePoint, OneDrive, Teams, Gruppen, Freigaben und Verantwortlichkeiten gemeinsam.'],
                ['enabled' => true, 'question' => 'Entsteht ein Governance Dokument?', 'answer' => 'Ja, als verständlicher Leitfaden mit Regeln, Rollen und nächsten Schritten.'],
                ['enabled' => true, 'question' => 'Kann ein bestehender Wildwuchs bereinigt werden?', 'answer' => 'Ja, der Workshop kann eine Bereinigungs-Roadmap und Quick Wins vorbereiten.'],
            ]]);
        } elseif ($proposal === 'admin-automation') {
            $page['internal_title'] = 'Admin Workshop und PowerShell Automatisierung';
            $page['public_title'] = 'Admin Workshop und PowerShell Automatisierung';
            $page['slug'] = 'admin-workshop-powershell-automatisierung';
            $page['template'] = 'technical';
            $page['hero']['badge_text'] = 'Admin Workshop · PowerShell · Betrieb';
            $page['hero']['title'] = 'Microsoft 365 Administration verständlich machen und wiederkehrende Aufgaben automatisieren';
            $page['hero']['subtitle'] = 'Workshops, Skripte, Dokumentation und Übergabe für Admin Teams.';
            $page['hero']['description'] = 'Diese Vorlage ist für Admin Enablement, PowerShell-Automatisierung und Betriebsübergabe mit passenden Platzhalterbereichen vorbereitet.';
            $updateSection($page, 'herausforderungen', [
                'title' => 'Typische Herausforderungen im Microsoft 365 Admin-Alltag',
                'intro' => 'Diese Vorlage ist für Admin Teams, die Wissen, Prozesse und wiederkehrende Aufgaben stabilisieren möchten.',
                'cards' => [
                    ['enabled' => true, 'icon' => '🧑‍💻', 'title' => 'Wissen verteilt', 'text' => 'Admin-Wissen steckt in Köpfen, Chats und alten Notizen statt in klaren Abläufen.'],
                    ['enabled' => true, 'icon' => '🔁', 'title' => 'Manuelle Routinen', 'text' => 'Wiederkehrende Prüfungen und Exporte werden manuell und uneinheitlich erledigt.'],
                    ['enabled' => true, 'icon' => '📉', 'title' => 'Fehlende Standards', 'text' => 'Skripte, Namensregeln und Dokumentation folgen keinem gemeinsamen Muster.'],
                    ['enabled' => true, 'icon' => '🚚', 'title' => 'Schwierige Übergabe', 'text' => 'Betrieb und Projektwissen lassen sich schwer an Teams übergeben.'],
                ],
            ]);
            $updateSection($page, 'leistungen', [
                'title' => 'Admin Workshop und Automation Bausteine',
                'intro' => 'Praxisnahe Inhalte für Microsoft 365 Admin Teams und Betriebsübergaben.',
                'columns' => 3,
                'cards' => [
                    ['enabled' => true, 'icon' => '🎓', 'category' => 'Workshop', 'title' => 'Admin Enablement', 'text' => 'Microsoft 365 Admin Center, Entra ID, Exchange, Teams und SharePoint praxisnah erklären.'],
                    ['enabled' => true, 'icon' => 'ti ti-settings-automation', 'category' => 'PowerShell', 'title' => 'Skript Grundlagen', 'text' => 'Wiederverwendbare PowerShell-Strukturen, Logging und sichere Ausführung aufbauen.'],
                    ['enabled' => true, 'icon' => '📊', 'category' => 'Reporting', 'title' => 'Admin Reports', 'text' => 'Benutzer, Gruppen, Lizenzen, Gastzugriffe und Berechtigungen nachvollziehbar auswerten.'],
                    ['enabled' => true, 'icon' => '🧪', 'category' => 'Betrieb', 'title' => 'Runbooks', 'text' => 'Wiederkehrende Aufgaben als klare Runbooks und Checklisten dokumentieren.'],
                    ['enabled' => true, 'icon' => '🔐', 'category' => 'Sicherheit', 'title' => 'Sichere Automatisierung', 'text' => 'Berechtigungen, App-Registrierungen und Secrets sauber einordnen.'],
                    ['enabled' => true, 'icon' => '📘', 'category' => 'Übergabe', 'title' => 'Dokumentation', 'text' => 'Wissen so festhalten, dass das Admin Team es im Alltag nutzen kann.'],
                ],
            ]);
            $updateSection($page, 'faq', ['title' => 'FAQ zu Admin Workshop und PowerShell', 'cards' => [
                ['enabled' => true, 'question' => 'Müssen PowerShell-Kenntnisse vorhanden sein?', 'answer' => 'Nein, Inhalte können von Grundlagen bis zu fortgeschrittener Automatisierung angepasst werden.'],
                ['enabled' => true, 'question' => 'Werden echte Skripte erstellt?', 'answer' => 'Ja, auf Wunsch entstehen wiederverwendbare Beispiele, Reports oder Runbooks für den Alltag.'],
                ['enabled' => true, 'question' => 'Ist die Übergabe dokumentiert?', 'answer' => 'Ja, Ziel ist eine verständliche Dokumentation für Betrieb und Weiterentwicklung.'],
            ]]);
        }

        $targetSectionOrder = ['proof', 'partner_band', 'comparison', 'services', 'steps', 'collaboration', 'trust', 'technology', 'html', 'booking', 'divider', 'cta'];
        $targetSectionNames = [
            'proof' => 'Grundlagen',
            'partner_band' => 'Partnerband',
            'comparison' => 'Vergleich GenAI Agentic AI',
            'services' => 'Meine Leistungen',
            'steps' => 'Beratungsablauf',
            'collaboration' => 'Zusammenarbeit',
            'trust' => 'Trust Bereich',
            'technology' => 'Technologie Bereich',
            'html' => 'Freier HTML Bereich',
            'booking' => 'Terminbuchung',
            'divider' => 'Zitat Trenner',
            'cta' => 'CTA Band Copilot Readiness',
        ];
        $ensureSection = static function (array &$sections, string $type, array $defaults): void {
            foreach ($sections as $section) {
                if (($section['type'] ?? '') === $type) {
                    return;
                }
            }
            $sections[] = $defaults + ['enabled' => true, 'type' => $type, 'cards' => []];
        };
        $ensureSection($page['sections'], 'proof', [
            'id' => 'belegbare-grundlagen',
            'anchor_id' => 'belegbare-grundlagen',
            'internal_name' => 'Grundlagen',
            'eyebrow' => 'Belegbare Grundlagen',
            'title' => 'Was nach Beratung greifbar wird',
            'intro' => 'Keine erfundenen Kundenzitate. Hier stehen nur nachvollziehbare Erfahrung, klare Projektbelege und später echte freigegebene Referenzen.',
            'columns' => 3,
            'card_type' => 'text',
            'card_design' => 'accent',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
            'cards' => [
                ['enabled' => true, 'badge' => 'Erfahrung', 'title' => '20+ Jahre Microsoft-Infrastruktur', 'text' => 'Senior IT-Admin mit Schwerpunkt Microsoft 365, Azure, Exchange, PowerShell, IT-Security und Datenschutz/Compliance.'],
                ['enabled' => true, 'badge' => 'Prüfung', 'title' => 'IHK-Prüfer', 'text' => 'Prüfungsperspektive aus Ausbildung und Praxis. Das hilft bei klaren Standards, verständlicher Übergabe und sauberer Dokumentation.'],
                ['enabled' => true, 'badge' => 'Zertifizierung', 'title' => 'Mehrfach zertifiziert', 'text' => 'LPIC 1 & 2 sowie Microsoft-Zertifizierungen ergänzen die praktische Erfahrung.'],
            ],
        ]);
        $ensureSection($page['sections'], 'partner_band', [
            'id' => 'partnerband',
            'anchor_id' => 'partnerband',
            'enabled' => false,
            'internal_name' => 'Partnerband',
            'eyebrow' => 'Netzwerk',
            'title' => 'Zugehörig zum copilotberater.de Netzwerk',
            'intro' => 'Einordnung, Netzwerkbezug und weiterführende Links zum Partnerangebot.',
            'partner_layout' => 'network-card',
            'button_1_text' => 'copilotberater.de',
            'button_1_target' => 'https://copilotberater.de',
            'button_1_target_type' => 'external',
            'button_1_style' => 'primary',
            'button_2_text' => 'Copilotberater Deutschland Karte',
            'button_2_target' => 'https://copilotberater.de/copilotberater-deutschland-karte/',
            'button_2_target_type' => 'external',
            'button_2_style' => 'ghost',
        ]);
        $ensureSection($page['sections'], 'booking', [
            'id' => 'termin-buchen',
            'anchor_id' => 'termin-buchen',
            'internal_name' => 'Terminbuchung',
            'eyebrow' => 'Termin',
            'title' => 'Direkt einen Termin buchen',
            'intro' => 'Wähle einen passenden Slot für ein erstes Gespräch zu Microsoft 365, Copilot oder Security. Danach klären wir Ziel, Ausgangslage und den nächsten sinnvollen Schritt.',
            'booking_url' => '',
            'booking_display' => 'embed',
            'booking_button_text' => 'Termin buchen',
            'background_color' => '#ffffff',
            'text_color' => '#111827',
        ]);
        $page['sections'] = array_values(array_filter($page['sections'], static fn(array $section): bool => in_array((string) ($section['type'] ?? ''), $targetSectionOrder, true)));
        usort($page['sections'], static function (array $a, array $b) use ($targetSectionOrder): int {
            $rankA = array_search((string) ($a['type'] ?? ''), $targetSectionOrder, true);
            $rankB = array_search((string) ($b['type'] ?? ''), $targetSectionOrder, true);
            return ($rankA === false ? 999 : $rankA) <=> ($rankB === false ? 999 : $rankB);
        });
        foreach ($page['sections'] as $index => &$section) {
            $section['sort_order'] = $index + 1;
            $type = (string) ($section['type'] ?? '');
            if (isset($targetSectionNames[$type])) {
                $section['internal_name'] = $targetSectionNames[$type];
            }
        }
        unset($section);
        return $page;
    }
}
