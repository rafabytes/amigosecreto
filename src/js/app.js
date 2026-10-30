/**
 * Entrada do front-end.
 *
 * - Swup troca o conteúdo de #site-header e #swup entre páginas, sem recarregar o navegador.
 * - Componentes React são montados como "ilhas" nos elementos [data-island] renderizados pelo PHP
 *   e desmontados antes de cada troca de página.
 */
import Swup from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';
import SwupProgressPlugin from '@swup/progress-plugin';
import { mountIslands, unmountIslands } from './islands';
import { mountGoogleButtons } from './lib/google';

const swup = new Swup({
	containers: ['#site-header', '#swup'],
	// Os dados mudam com frequência (listas, sorteios): sempre buscar a página atualizada.
	cache: false,
	ignoreVisit: (url, { el } = {}) =>
		Boolean(el?.closest('[data-no-swup]')) || /\/wp-(admin|login)|wp-login\.php/.test(url),
	plugins: [new SwupHeadPlugin({ persistAssets: true }), new SwupProgressPlugin({ delay: 250 })],
});

window.swup = swup;

swup.hooks.before('content:replace', () => unmountIslands());
swup.hooks.on('page:view', () => {
	mountIslands();
	mountGoogleButtons();
});

mountIslands();
mountGoogleButtons();

/*
 * Botão "mostrar senha" dos formulários PHP (as_password_toggle()). Delegado no document para
 * continuar funcionando depois das trocas de página do Swup.
 */
document.addEventListener('click', (event) => {
	const button = event.target.closest('[data-password-toggle]');
	const input = button?.parentElement.querySelector('input');
	if (!input) return;

	const show = input.type === 'password';
	input.type = show ? 'text' : 'password';
	button.setAttribute('aria-pressed', String(show));
	button.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
	button.title = button.getAttribute('aria-label');
	input.focus();
});

// Volta a ocultar antes de enviar, para o navegador oferecer salvar a senha normalmente.
document.addEventListener('submit', (event) => {
	event.target.querySelectorAll('[data-password-toggle][aria-pressed="true"]').forEach((button) => button.click());
});
