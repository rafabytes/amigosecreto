/**
 * Cliente da API REST do tema (amigosecreto/v1). Configuração injetada pelo PHP em window.AmigoSecreto.
 */
export const config = window.AmigoSecreto || {};

export async function api(path, { method = 'GET', body } = {}) {
	// FormData (upload de arquivo) vai como multipart; o navegador define o Content-Type.
	const isForm = body instanceof FormData;
	const response = await fetch(config.restUrl + path.replace(/^\//, ''), {
		method,
		credentials: 'same-origin',
		headers: {
			'X-WP-Nonce': config.nonce,
			...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
		},
		body: body ? (isForm ? body : JSON.stringify(body)) : undefined,
	});

	const data = await response.json().catch(() => null);

	if (!response.ok) {
		throw new Error(data?.message || 'Algo deu errado. Tente novamente.');
	}

	return data;
}

/** Navega usando o Swup quando disponível. */
export function navigate(url) {
	if (window.swup) {
		window.swup.navigate(url);
	} else {
		window.location.href = url;
	}
}
