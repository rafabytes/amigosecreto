import { useRef, useState } from 'react';
import { Avatar, Notice, PasswordInput, Spinner } from '../components/ui';
import { api } from '../lib/api';

/**
 * Perfil do usuário logado: foto, dados, contas Google/Facebook e senha.
 * `notice` traz o resultado de "Conectar Google/Facebook" (redirecionamento do PHP).
 */
export default function ProfileForm({ initialProfile, notice = null, today }) {
	const [profile, setProfile] = useState(initialProfile);

	return (
		<div className="space-y-6">
			{notice && <Notice type={notice.type}>{notice.message}</Notice>}
			<PhotoCard profile={profile} onChange={setProfile} />
			<DetailsForm profile={profile} onChange={setProfile} today={today} />
			{profile.providers.length > 0 && <AccountsCard profile={profile} onChange={setProfile} />}
		</div>
	);
}

function PhotoCard({ profile, onChange }) {
	const input = useRef(null);
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');

	async function run(request) {
		setBusy(true);
		setError('');
		try {
			onChange(await request());
		} catch (err) {
			setError(err.message);
		} finally {
			setBusy(false);
			if (input.current) input.current.value = '';
		}
	}

	function upload(event) {
		const file = event.target.files?.[0];
		if (!file) return;
		if (file.size > 5 * 1024 * 1024) {
			setError('A imagem pode ter no máximo 5 MB.');
			return;
		}
		const body = new FormData();
		body.append('avatar', file);
		run(() => api('me/avatar', { method: 'POST', body }));
	}

	return (
		<section className="card flex flex-col items-center gap-5 p-6 sm:flex-row sm:p-8">
			<div className="relative">
				<Avatar user={profile} className="size-24 text-3xl" />
				{busy && (
					<span className="absolute inset-0 grid place-items-center rounded-full bg-white/70">
						<Spinner className="size-6 text-grape" />
					</span>
				)}
			</div>
			<div className="flex-1 text-center sm:text-left">
				<h2 className="text-lg font-bold">Foto de perfil</h2>
				<p className="text-sm text-ink/60">Opcional. Ajuda os participantes a reconhecerem você.</p>
				<div className="mt-4 flex flex-wrap justify-center gap-2 sm:justify-start">
					<label className={`btn btn-soft btn-sm ${busy ? 'pointer-events-none opacity-50' : ''}`}>
						{profile.avatar ? 'Trocar foto' : 'Enviar foto'}
						<input ref={input} type="file" accept="image/jpeg,image/png,image/webp,image/gif" className="sr-only" onChange={upload} disabled={busy} />
					</label>
					{profile.avatar && (
						<button type="button" className="btn btn-sm text-ink/50 hover:text-berry" disabled={busy} onClick={() => run(() => api('me/avatar', { method: 'DELETE' }))}>
							Remover
						</button>
					)}
				</div>
				<div className="mt-3">
					<Notice type="error">{error}</Notice>
				</div>
			</div>
		</section>
	);
}

function DetailsForm({ profile, onChange, today }) {
	const [values, setValues] = useState({
		name: profile.name,
		email: profile.email,
		birthdate: profile.birthdate || '',
		currentPassword: '',
		newPassword: '',
	});
	const [status, setStatus] = useState({ type: '', message: '' });
	const [saving, setSaving] = useState(false);

	const emailChanged = values.email.trim().toLowerCase() !== profile.email.toLowerCase();
	const needsCurrent = !profile.needsPassword && (values.newPassword !== '' || emailChanged);
	const set = (key) => (event) => setValues((v) => ({ ...v, [key]: event.target.value }));

	async function submit(event) {
		event.preventDefault();
		setSaving(true);
		setStatus({ type: '', message: '' });
		try {
			const updated = await api('me', { method: 'POST', body: values });
			onChange(updated);
			setValues({ name: updated.name, email: updated.email, birthdate: updated.birthdate || '', currentPassword: '', newPassword: '' });
			setStatus({ type: 'success', message: 'Perfil atualizado!' });
		} catch (err) {
			setStatus({ type: 'error', message: err.message });
		} finally {
			setSaving(false);
		}
	}

	return (
		<form onSubmit={submit} className="card space-y-5 p-6 sm:p-8">
			<label className="field">
				<span className="field-label">Nome</span>
				<input className="input" value={values.name} onChange={set('name')} autoComplete="name" required />
				<span className="field-hint">É assim que você aparece para os outros participantes.</span>
			</label>

			<div className="grid gap-5 sm:grid-cols-[1fr_12rem]">
				<label className="field">
					<span className="field-label">E-mail</span>
					<input className="input" type="email" value={values.email} onChange={set('email')} autoComplete="email" required />
				</label>
				<label className="field">
					<span className="field-label">Data de nascimento</span>
					<input className="input" type="date" value={values.birthdate} onChange={set('birthdate')} max={today} />
				</label>
			</div>
			<p className="-mt-3 text-xs text-ink/50">Opcional. Os participantes veem só o dia e o mês do seu aniversário.</p>

			<fieldset className="space-y-5 border-t border-ink/5 pt-5">
				<legend className="sr-only">Senha</legend>
				{profile.needsPassword && (
					<Notice>Você ainda não criou uma senha. Crie uma para também poder entrar com e-mail e senha.</Notice>
				)}
				<label className="field">
					<span className="field-label">{profile.needsPassword ? 'Criar senha' : 'Nova senha'}</span>
					<PasswordInput
						value={values.newPassword}
						onChange={set('newPassword')}
						autoComplete="new-password"
						minLength={8}
						placeholder={profile.needsPassword ? 'Mínimo de 8 caracteres' : 'Deixe em branco para manter a atual'}
					/>
				</label>

				{needsCurrent && (
					<label className="field">
						<span className="field-label">Senha atual *</span>
						<PasswordInput value={values.currentPassword} onChange={set('currentPassword')} autoComplete="current-password" required />
						<span className="field-hint">Necessária para alterar e-mail ou senha.</span>
					</label>
				)}
			</fieldset>

			<Notice type={status.type}>{status.message}</Notice>

			<div className="flex justify-end">
				<button type="submit" className="btn btn-primary" disabled={saving}>
					{saving && <Spinner />}
					Salvar alterações
				</button>
			</div>
		</form>
	);
}

function AccountsCard({ profile, onChange }) {
	const [busy, setBusy] = useState('');
	const [error, setError] = useState('');

	async function unlink(provider) {
		if (!window.confirm(`Desconectar sua conta ${provider.label}? Você poderá conectá-la de novo depois.`)) return;
		setBusy(provider.slug);
		setError('');
		try {
			onChange(await api(`me/social/${provider.slug}`, { method: 'DELETE' }));
		} catch (err) {
			setError(err.message);
		} finally {
			setBusy('');
		}
	}

	return (
		<section className="card p-6 sm:p-8">
			<h2 className="text-lg font-bold">Contas conectadas</h2>
			<p className="text-sm text-ink/60">Entre com um clique usando Google ou Facebook.</p>
			<ul className="mt-5 divide-y divide-ink/5">
				{profile.providers.map((provider) => (
					<li key={provider.slug} className="flex items-center justify-between gap-4 py-3">
						<span className="font-medium">
							{provider.label}
							{provider.linked && <span className="chip chip-success ml-2">conectado</span>}
						</span>
						{provider.linked ? (
							<button type="button" className="btn btn-ghost btn-sm" disabled={busy === provider.slug} onClick={() => unlink(provider)}>
								{busy === provider.slug && <Spinner />}
								Desconectar
							</button>
						) : (
							provider.connectUrl && (
								<a href={provider.connectUrl} className="btn btn-soft btn-sm" data-no-swup>
									Conectar
								</a>
							)
						)}
					</li>
				))}
			</ul>
			<Notice type="error">{error}</Notice>
		</section>
	);
}
