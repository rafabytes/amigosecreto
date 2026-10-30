/**
 * Botão oficial "Sign in with Google" (Google Identity Services).
 *
 * O Google entrega um ID token (JWT) a este script, que o envia para a API do tema
 * (POST auth/google). Não usamos o redirecionamento OAuth porque o retorno do Google traz
 * iss=https://accounts.google.com, que o firewall da hospedagem (ModSecurity) bloqueia.
 */
import { api, config } from './api';

let loader = null;

function loadGoogle() {
	if (window.google?.accounts?.id) return Promise.resolve(window.google);
	if (!loader) {
		loader = new Promise((resolve, reject) => {
			const script = document.createElement('script');
			script.src = 'https://accounts.google.com/gsi/client';
			script.async = true;
			script.onload = () => resolve(window.google);
			script.onerror = () => {
				loader = null;
				reject(new Error('Não foi possível carregar o login do Google. Verifique sua conexão.'));
			};
			document.head.appendChild(script);
		});
	}
	return loader;
}

/**
 * Renderiza o botão do Google em `el`. `onCredential` recebe o ID token.
 */
export async function renderGoogleButton(el, { onCredential, text = 'continue_with' }) {
	if (!config.googleClientId) return;

	const [google, { nonce }] = await Promise.all([loadGoogle(), api('auth/google/nonce')]);

	google.accounts.id.initialize({
		client_id: config.googleClientId,
		nonce,
		ux_mode: 'popup',
		context: 'signin',
		itp_support: true,
		callback: (response) => onCredential(response.credential),
	});

	el.replaceChildren();
	google.accounts.id.renderButton(el, {
		type: 'standard',
		theme: 'outline',
		size: 'large',
		shape: 'pill',
		text,
		logo_alignment: 'center',
		locale: 'pt-BR',
		width: Math.max(200, Math.min(400, Math.round(el.getBoundingClientRect().width) || 320)),
	});
}

/**
 * Botões dos formulários PHP (as_social_buttons()): entra e vai para data-redirect.
 */
export function mountGoogleButtons(scope = document) {
	scope.querySelectorAll('[data-google-signin]:not([data-mounted])').forEach((el) => {
		el.dataset.mounted = '1';

		const showError = (message) => {
			let notice = el.nextElementSibling?.matches('.google-signin-error') ? el.nextElementSibling : null;
			if (!notice) {
				notice = document.createElement('p');
				notice.className = 'google-signin-error notice notice-error';
				notice.setAttribute('role', 'alert');
				el.after(notice);
			}
			notice.textContent = message;
		};

		renderGoogleButton(el, {
			onCredential: async (credential) => {
				el.setAttribute('aria-busy', 'true');
				try {
					const { redirect } = await api('auth/google', {
						method: 'POST',
						body: { credential, redirectTo: el.dataset.redirect || '' },
					});
					// Recarrega a página inteira: cabeçalho, nonce e dados do usuário mudam após o login.
					window.location.assign(redirect);
				} catch (error) {
					el.removeAttribute('aria-busy');
					showError(error.message);
				}
			},
		}).catch((error) => showError(error.message));
	});
}
