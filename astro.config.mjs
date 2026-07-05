// @ts-check

import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';
import { defineConfig, fontProviders } from 'astro/config';

// https://astro.build/config
export default defineConfig({
	site: 'https://walter-uehli.ch',
	integrations: [mdx(), sitemap()],
	fonts: [
		{
			// Headlines – kondensiert, kraftvoll (greift den Logo-Schriftzug auf)
			provider: fontProviders.google(),
			name: 'Oswald',
			cssVariable: '--font-display',
			fallbacks: ['Impact', 'sans-serif'],
			weights: [500, 600, 700],
		},
		{
			// Fließtext – Buchwärme
			provider: fontProviders.google(),
			name: 'Source Serif 4',
			cssVariable: '--font-serif',
			fallbacks: ['Georgia', 'serif'],
			weights: [400, 600],
			styles: ['normal', 'italic'],
		},
		{
			// UI & Meta – funktional
			provider: fontProviders.google(),
			name: 'Inter',
			cssVariable: '--font-sans',
			fallbacks: ['system-ui', 'sans-serif'],
			weights: [400, 500, 600],
		},
	],
});
