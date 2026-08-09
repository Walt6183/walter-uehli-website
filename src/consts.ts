// Zentrale Website-Daten. Von überall importierbar via `import { ... } from '../consts'`.

export const SITE_TITLE = 'Walter Uehli';
export const SITE_TAGLINE = 'Atmosphärische Thriller zwischen Schweiz und Südtirol';
export const SITE_DESCRIPTION =
	'Atmosphärische Thriller zwischen der Schweiz und Südtirol. Fred Huwiler ermittelt: von der KaPo Aargau bis in die Südtiroler Berge. Von Walter Uehli.';

// Primärer Claim (CI)
export const CLAIM = '«Wo die Berge schweigen, beginnt das Verbrechen.»';

// Kanonische Domain der Live-Seite (für Canonical-URLs, Sitemap, Social-Cards).
export const SITE_URL = 'https://walter-uehli.ch';

// Autor
export const AUTHOR = {
	name: 'Walter Uehli',
	role: 'Autor',
	email: 'info@walter-uehli.ch',
	phone: '+41 79 100 84 46',
	phoneHref: '+41791008446',
};

// Aktuelles Buch – Band 1 der Fred-Huwiler-Reihe
export const BOOK = {
	title: 'Huwiler und der Organhandel',
	series: 'Die Fred-Huwiler-Reihe · Band 1',
	releaseDate: '16. Juli 2026',
	tagline: 'Ein harter Thriller über Verantwortung, Schuld und den Wert eines Menschenlebens.',
	// Offizieller Klappentext, in Absätzen.
	blurbParagraphs: [
		'Fred Huwiler ist in Südtirol im Urlaub. Abstand gewinnen, zur Ruhe kommen, Zeit fernab von Ermittlungen. Doch als er auf Hinweise stösst, die auf einen organisierten Handel mit Organen hindeuten, erinnert er sich an etwas, das er nicht verdrängen kann. Die Erinnerung an ein Versprechen, das er seinem Jugendfreund vor Jahren gegeben hat. Ein Versprechen, das Verantwortung bedeutet.',
		'Seine Recherchen führen von Afrika über Südtirol bis nach Deutschland, an Orte, die harmlos wirken und gerade deshalb so gefährlich sind. Hinter verschlossenen Türen verbinden sich Geld, Einfluss und Institutionen zu einem System, das sich selbst schützt. Ein Geflecht aus Macht, Abhängigkeit und Schweigen, in dem Menschen zu Zahlen werden und Leben verhandelbar sind.',
	],
};

// Social-Media-Profile
export const SOCIAL = {
	instagram: 'https://www.instagram.com/walter.uehli.autor/',
	facebook: 'https://www.facebook.com/walter.uehli.autor/',
	linkedin: 'https://www.linkedin.com/in/walter-uehli',
	whatsapp: 'https://wa.me/41791008446',
};

// Formulare (Kontakt & Testleser)
// Standard: PHP-Handler auf dem Hostinger-Server (public/kontakt.php → dist/kontakt.php).
// Alternative: eine Formspree-URL eintragen (z. B. 'https://formspree.io/f/XXXX').
export const FORM_ENDPOINT = '/kontakt.php';

// Newsletter (CleverReach). Aktuell: Verlinkung auf das bestehende CleverReach-Anmeldeformular.
// Optional später durch ein eingebettetes CleverReach-Formular ersetzen.
export const NEWSLETTER_URL =
	'https://flow.cleverreach.com/fl/a7211b99-8f77-4bfe-ba60-12d1465fb08a/';

// Hauptnavigation (deutsche Slugs – kompatibel zur bestehenden Seitenstruktur)
export const NAV = [
	{ href: '/', label: 'Start' },
	{ href: '/das-buch', label: 'Das Buch' },
	{ href: '/uber-mich', label: 'Über mich' },
	{ href: '/blog', label: 'Blog' },
	{ href: '/kontakt', label: 'Kontakt' },
];
