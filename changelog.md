# Changelog - Drupal TV 2.0

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
