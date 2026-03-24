# Changelog - Drupal TV 2.0

## [2026-03-24] – Tutorial-Inhaltstyp & Redaktionsworkflow

### Fehlerbehebungen
- `TypeError` in `SchemaDotOrgDescriptionsConfigFactoryOverride::getCustomDescription()` behoben (Null-Coalescing-Operator `?? []` auf Zeile 270).
- Fehlende `block_content`-Datenbanktabellen repariert (manuell durch Nutzer via SQL).
- `TypeError` in `canvas` Modul `BlockComponent::renderComponent()` behoben: `$block->build() ?? []` verhindert Absturz bei Block-Plugins, die `null` zurückgeben (Zeile 247 in `BlockComponent.php`).

### Neue Module (installiert & aktiviert)
- `drupal/type_tray` (1.3.2) + `drupal/gin_type_tray` (1.0.0) – verbesserte Inhaltstyp-Auswahl im Backend.
- `drupal/paragraphs` (1.20.0) + `drupal/entity_reference_revisions` (1.14.0) – Grundlage für strukturierte Lernschritte.
- `drupal/gemini_provider` (1.0.1) inkl. `drupal/ai` + `drupal/key` – Vorbereitung für KI-gestützte Inhaltsgenerierung.
- `schemadotorg_blueprints` (inkl. aller Submodule) – automatisches Mapping von Drupal-Inhaltstypen zu Schema.org.

### Inhaltstyp: Tutorial (`node/tutorial`)
- Inhaltstyp `tutorial` neu erstellt auf Basis des `HowTo`-Schema.org-Blueprints.
- Inhaltstyp-Beschreibung auf Deutsch gesetzt.
- Felder automatisch via `SchemaDotOrgMappingManager::createType()` generiert (Skript: `web/create_tutorial.php`).
- Feld `schema_step` (einfacher String) ersetzt durch `field_tutorial_steps` (Paragraph-Referenz, unbegrenzte Kardinalität, Typ: `entity_reference_revisions`).
- Schema.org-Mapping aktualisiert: `step` → `field_tutorial_steps`.
- Alle Feld-Labels auf Deutsch übersetzt und ausführliche, anfängerfreundliche Hilfetexte für alle Felder hinterlegt.

### Paragraph-Typ: Lernschritt (`paragraph/tutorial_step`)
- Neuer Paragraph-Typ `tutorial_step` erstellt auf Basis des `HowToStep`-Schema.org-Blueprints.
- Felder: `schema_name` (Schritt-Überschrift), `schema_description` (Ausführliche Anweisung), `schema_item_list_element` (Elemente).
- Alle Feld-Labels und Hilfetexte auf Deutsch gesetzt.

### Custom Modul: `tutorial_workflow`
- Neues Custom-Modul `web/modules/custom/tutorial_workflow` erstellt (Drupal 11 kompatibel).
- `hook_install()`: Erstellt Feld `field_tutorial_checklist` (Typ: `list_string`) mit 10 feldspezifischen Checklisten-Punkten als `options_buttons`-Widget in der Formularsidebar.
- `hook_form_node_form_alter()`: Verschiebt die Checkliste in einen `<details>`-Container in der rechten Sidebar (`#group => 'advanced'`).
- Custom Validation Handler: Verhindert das Veröffentlichen (Status = 1), solange nicht alle 10 Checklisten-Punkte abgehakt sind. Beim Speichern als Entwurf wird kein Fehler geworfen.

### Inhaltstypen: Blog-Beitrag & Inhaltsseite (Übersetzung)
- `blog` umbenannt zu „Blog-Beitrag" mit deutscher Beschreibung.
- `page` umbenannt zu „Inhaltsseite" mit deutscher Beschreibung.
- Alle Felder beider Inhaltstypen ins Deutsche übersetzt (Labels + Hilfetexte): `field_blog__byline`, `field_component_tree`, `field_content`, `field_description`, `field_featured_image`, `field_seo_analysis`, `field_seo_description`, `field_seo_image`, `field_seo_title`, `field_tags`.

### Dokumentation
- Autorenleitfaden erstellt: `brain/tutorial_autor_leitfaden.md` – erklärt alle Tutorial-Felder für Anfänger inkl. Schema.org-Hintergrund und Veröffentlichungs-Checkliste.
- Temporäre Skripte erstellt (zum Aufräumen vorgemerkt): `web/create_tutorial.php`, `web/export_fields.php`, `web/export_paragraph_fields.php`, `web/export_content_types.php`, `web/replace_tutorial_step.php`, `web/update_tutorial_help_texts.php`, `web/translate_content_types.php`, `web/update_checklist_values.php`, `web/fix_block.php`.

## [2026-03-24] - Initialisierung
- Projektstruktur analysiert (Drupal CMS 2.x / Drupal 11).
- `AGENTS.md` Verhaltensregeln verarbeitet.
- `changelog.md` angelegt und Korrektur des Dateinamens durchgeführt.
- GitHub CLI (`gh`) installiert und Authentifizierungsstatus geprüft.
- Lokales Git-Repository initialisiert und Branch auf `main` gesetzt.
- Neues GitHub Repository `nodedropweb/drupal_tv_2.0` angelegt.
- Initialer Push aller Projektdaten durchgeführt.
- `schema_metatag` und Submodule (WebPage, WebSite, VideoObject, QAPage, FAQPage, Person, ImageObject, HowTo, Course, Article) installiert und aktiviert.
- Lokale Drush-Konfiguration (`drush/drush.yml`) mit Site-URI (`https://dev.sithis.xyz`) erstellt für einfachere CLI-Nutzung.
- `hero-blog.twig` im Byte Theme (`contrib`) angepasst: "By"-String übersetzbar gemacht (`{% trans %}`). Mit `git add -f` committet (temporär, da MR auf drupal.org bereits in Review).
- Modul `estimated_read_time` (v1.2.1) via Composer installiert und aktiviert für die automatische Berechnung der Lesedauer.
