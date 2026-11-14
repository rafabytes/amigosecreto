/**
 * Registra as páginas vistas no histórico de atividade (inc/activity.php), visível só para
 * administradores. Chamado no carregamento e a cada navegação do Swup; só para usuários logados.
 */
import { config } from './api';

let last = '';

export function trackView() {
	if (!config.user || !config.restUrl) return;

	const path = window.location.pathname + window.location.search;
	if (path === last) return;
	last = path;

	fetch(`${config.restUrl}activity/view`, {
		method: 'POST',
		credentials: 'same-origin',
		keepalive: true,
		headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
		body: JSON.stringify({ path, title: document.title }),
	}).catch(() => {});
}
