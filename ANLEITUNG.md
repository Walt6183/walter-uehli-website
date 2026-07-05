# walter-uehli.ch – neue Website (Astro)

Relaunch der Autorenseite. Schnell, SEO-freundlich, umgesetzt nach dem
CI-Leitfaden «Brand Guidelines V2.1».

## ⚠️ Wichtig: Speicherort

Das Projekt liegt neu unter **`/Users/walteruehli/walter-uehli-web`** –
also **ausserhalb** des kDrive-Sync-Ordners.

Grund: Im kDrive-Ordner («Webseite Autor») hat die Cloud-Synchronisation während
der Entwicklung laufend Dateien auf ältere Stände zurückgesetzt und Konfliktkopien
erzeugt. Ausserhalb der Synchronisation ist die Entwicklung stabil.
Das fertige Ergebnis kann jederzeit in den kDrive-Ordner kopiert werden – ideal ist
aber, die Seite über Git zu hosten (siehe unten) und kDrive nur als Backup zu nutzen.

## Lokal ansehen & bearbeiten

```bash
cd /Users/walteruehli/walter-uehli-web
npm install        # einmalig
npm run dev        # Vorschau unter http://localhost:4321
npm run build      # fertige Seite in /dist (zum Veröffentlichen)
```

## CI-Umsetzung (nach Brand Guidelines V2.1)

- **Farben:** Crimson Blood `#8B1A2B` (sparsamer Akzent), Noir/Charcoal Flächen,
  Warm Parchment Text – definiert in `src/styles/global.css`.
- **Schriften:** Oswald (Headlines), Source Serif 4 (Fliesstext), Inter (UI/Meta) –
  via Google Fonts, konfiguriert in `astro.config.mjs`.
- **Logo:** `public/logo-header.png` (invertiert, für dunklen Hintergrund).
- **Cover:** `public/cover.png` (E-Book-Mockup).
- **Autorenfoto:** `public/walter-uehli.jpg` (nur 150×150 – bitte höher aufgelöstes
  Foto liefern, dann ersetzen).
- **Claim, Bio, Meta-Description, Buchtexte** aus dem CI übernommen.

## Aufbau

```
src/
  consts.ts            → Autor, Buch, Claim, Social-Links, Navigation
  pages/               → index, das-buch, uber-mich, kontakt, impressum, datenschutz, blog/
  content/blog/        → Blogbeiträge als Markdown (.md)
  components/          → Header, Footer, Newsletter, BookCover, SocialIcons …
  styles/global.css    → Design-System (CI)
```

## Blogbeitrag schreiben

Neue Datei in `src/content/blog/`, z. B. `mein-beitrag.md`:

```markdown
---
title: 'Titel'
description: 'Kurzbeschreibung (Vorschau & Google)'
pubDate: 2026-07-10
---

Text als **Markdown**.
```

## Noch offen

1. **Echte Blog-Volltexte** aus WordPress in die 10 `.md`-Dateien übertragen
   (aktuell Kurz-Platzhalter).
2. **Höher aufgelöstes Autorenfoto** (aktuell nur 150 px).
3. **Formulare verbinden:** Kontakt/Testleser (z. B. Formspree) + Newsletter
   («Tatort-Post», z. B. Brevo/Mailchimp). Stellen: `Newsletter.astro`, `kontakt.astro`.
4. **Social-Links** in `src/consts.ts` prüfen (Facebook/LinkedIn Platzhalter).
5. **Impressum/Datenschutz** mit echten Angaben füllen.
6. **Favicon** aus der Logo-Bildmarke (Silhouette) erzeugen.
7. **Optional:** Git-CMS (Sveltia) für komfortables Bloggen im Browser.

## Veröffentlichen (Hosting)

Empfohlen: **Netlify** oder **Cloudflare Pages** (kostenlos). Projekt in ein
Git-Repository legen, verbinden, Build-Befehl `npm run build`, Ausgabeordner `dist`.
Danach geht jede Änderung automatisch live – und der kDrive-Sync-Konflikt entfällt.
