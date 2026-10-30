import { useState } from 'react';
import GroupForm from '../components/GroupForm';
import { Avatar, Icon, Modal, Notice, Spinner } from '../components/ui';
import { api, config, navigate } from '../lib/api';

/**
 * Página do grupo. Todos veem detalhes e participantes; participantes descobrem quem tiraram;
 * o organizador (ou admin) gerencia participantes e faz o sorteio.
 */
export default function GroupPage({ initialGroup }) {
	const [group, setGroup] = useState(initialGroup);
	const [editing, setEditing] = useState(false);

	async function save(values) {
		setGroup(await api(`groups/${group.id}`, { method: 'POST', body: values }));
		setEditing(false);
	}

	async function remove() {
		if (!window.confirm(`Excluir o grupo "${group.title}"? Essa ação não pode ser desfeita.`)) return;
		const result = await api(`groups/${group.id}`, { method: 'DELETE' });
		navigate(result.redirect);
	}

	// Reflete na hora o progresso "X de Y já viram" quando o próprio organizador revela.
	function markRevealed() {
		setGroup((g) => ({
			...g,
			participants: g.participants.map((p) => (p.id === config.user?.id && 'revealed' in p ? { ...p, revealed: true } : p)),
		}));
	}

	return (
		<div className="mt-4">
			<GroupHeader group={group} onEdit={() => setEditing(true)} onDelete={remove} />

			<div className="mt-10 grid gap-8 lg:grid-cols-[1fr_22rem]">
				<div className="space-y-8">
					{group.isParticipant && <SecretFriend group={group} onReveal={markRevealed} />}
					<Participants group={group} onChange={setGroup} />
				</div>

				{group.isManager && (
					<aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
						<DrawPanel group={group} onChange={setGroup} />
						<AddParticipant group={group} onChange={setGroup} />
					</aside>
				)}
			</div>

			{group.isManager && (
				<Modal open={editing} onClose={() => setEditing(false)} title="Editar grupo">
					<GroupForm
						initial={{
							title: group.title,
							description: group.description,
							eventDate: group.eventDate,
							location: group.location,
							budget: group.budget,
							joinAsParticipant: group.isParticipant,
						}}
						joinDisabled={group.isDrawn}
						onSubmit={save}
						onCancel={() => setEditing(false)}
					/>
				</Modal>
			)}
		</div>
	);
}

function GroupHeader({ group, onEdit, onDelete }) {
	const details = [
		group.eventDateText && { icon: 'calendar', text: group.eventDateText },
		group.location && { icon: 'pin', text: group.location },
		group.budget && { icon: 'money', text: group.budget },
	].filter(Boolean);

	return (
		<header>
			<div className="flex flex-wrap items-start justify-between gap-4">
				<div>
					<h1 className="text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">{group.title}</h1>
					<p className="mt-2 text-ink/60">
						Organizado por <strong className="font-semibold text-ink/80">{group.organizer?.name}</strong>
					</p>
				</div>
				{group.isManager && (
					<div className="flex gap-2">
						<button type="button" className="btn btn-ghost btn-sm" onClick={onEdit}>
							Editar
						</button>
						<button type="button" className="btn btn-danger btn-sm" onClick={onDelete}>
							Excluir
						</button>
					</div>
				)}
			</div>

			{details.length > 0 && (
				<ul className="mt-5 flex flex-wrap gap-2">
					{details.map((item) => (
						<li key={item.icon} className="chip chip-grape px-3 py-1.5 text-sm">
							<Icon name={item.icon} className="size-4" />
							{item.text}
						</li>
					))}
				</ul>
			)}

			{group.description && <p className="mt-5 max-w-2xl whitespace-pre-line text-ink/70">{group.description}</p>}
		</header>
	);
}

/** Cartão "quem eu tirei", com revelação por clique. */
function SecretFriend({ group, onReveal }) {
	const [match, setMatch] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');

	async function reveal() {
		setLoading(true);
		setError('');
		try {
			setMatch((await api(`groups/${group.id}/reveal`, { method: 'POST' })).match);
			onReveal();
		} catch (err) {
			setError(err.message);
		} finally {
			setLoading(false);
		}
	}

	if (!group.isDrawn) {
		return (
			<section className="card flex flex-col items-start gap-4 p-6 sm:flex-row sm:items-center sm:p-8">
				<span className="text-4xl" aria-hidden="true">⏳</span>
				<div className="flex-1">
					<h2 className="text-lg font-bold">O sorteio ainda não aconteceu</h2>
					<p className="text-ink/60">Você vai receber um e-mail quando o organizador sortear. Enquanto isso, capriche na sua lista!</p>
				</div>
				<a href={config.routes.wishlist} className="btn btn-soft">
					<Icon name="gift" className="size-4" />
					Minha lista
				</a>
			</section>
		);
	}

	if (!match) {
		return (
			<section className="relative overflow-hidden rounded-3xl bg-ink p-8 text-center text-white sm:p-10">
				<div aria-hidden="true" className="pointer-events-none absolute -top-24 left-1/2 size-72 -translate-x-1/2 rounded-full bg-grape/50 blur-3xl" />
				<div className="relative">
					<p className="text-5xl" aria-hidden="true">🎁</p>
					<h2 className="mt-4 text-2xl font-bold">O sorteio foi realizado!</h2>
					<p className="mt-1 text-white/70">Confira se ninguém está olhando a sua tela…</p>
					<button type="button" className="btn btn-accent btn-lg mt-6" onClick={reveal} disabled={loading}>
						{loading ? <Spinner /> : <Icon name="sparkles" />}
						Descobrir quem eu tirei
					</button>
					<div className="mx-auto mt-4 max-w-sm">
						<Notice type="error">{error}</Notice>
					</div>
				</div>
			</section>
		);
	}

	return (
		<section className="animate-reveal relative overflow-hidden rounded-3xl bg-linear-to-br from-grape to-berry p-8 text-white shadow-xl shadow-grape/30 sm:p-10">
			<p className="text-sm font-semibold tracking-wide text-white/70 uppercase">Você tirou</p>
			<div className="mt-4 flex flex-wrap items-center gap-4">
				{match.avatar ? (
					<img src={match.avatar} alt="" className="size-16 rounded-full object-cover ring-4 ring-white/30" />
				) : (
					<span className="grid size-16 place-items-center rounded-full bg-white/20 text-2xl font-bold">{match.initials}</span>
				)}
				<p className="text-3xl font-extrabold tracking-tight sm:text-4xl">{match.name}</p>
			</div>
			<div className="mt-8 flex flex-wrap items-center gap-3">
				<a href={match.listUrl} className="btn btn-lg bg-white text-grape hover:bg-white/90">
					<Icon name="gift" />
					Ver lista de presentes
				</a>
				<span className="text-sm text-white/75">
					{match.gifts > 0 ? `${match.gifts} ${match.gifts === 1 ? 'ideia' : 'ideias'} na lista` : 'A lista ainda está vazia'}
				</span>
			</div>
			<button type="button" className="mt-6 text-sm text-white/60 underline hover:text-white" onClick={() => setMatch(null)}>
				Esconder
			</button>
		</section>
	);
}

function Participants({ group, onChange }) {
	const [busy, setBusy] = useState(0);
	const [error, setError] = useState('');

	async function removeParticipant(person) {
		if (!window.confirm(`Remover ${person.name} do grupo?`)) return;
		setBusy(person.id);
		setError('');
		try {
			onChange(await api(`groups/${group.id}/participants/${person.id}`, { method: 'DELETE' }));
		} catch (err) {
			setError(err.message);
		} finally {
			setBusy(0);
		}
	}

	return (
		<section>
			<div className="flex items-baseline justify-between gap-4">
				<h2 className="text-xl font-bold">Participantes</h2>
				<span className="text-sm text-ink/50">{group.participants.length}</span>
			</div>

			<Notice type="error">{error}</Notice>

			{group.participants.length === 0 ? (
				<div className="card mt-4 p-10 text-center text-ink/60">
					<p className="text-4xl" aria-hidden="true">👥</p>
					<p className="mt-3">Nenhum participante ainda. Adicione pelo e-mail ao lado.</p>
				</div>
			) : (
				<ul className="card mt-4 divide-y divide-ink/5">
					{group.participants.map((person) => (
						<li key={person.id} className="flex items-center gap-4 p-4 sm:px-6">
							<Avatar user={person} />
							<div className="min-w-0 flex-1">
								<p className="flex flex-wrap items-center gap-2 font-semibold">
									{person.name}
									{person.id === config.user?.id && <span className="chip">você</span>}
									{person.id === group.organizer?.id && <span className="chip chip-grape">organizador</span>}
								</p>
								<p className="truncate text-sm text-ink/50">
									{group.isManager && person.email ? `${person.email} · ` : ''}
									{person.gifts > 0 ? `${person.gifts} ${person.gifts === 1 ? 'presente' : 'presentes'} na lista` : 'lista vazia'}
								</p>
							</div>

							{group.isManager && group.isDrawn && (
								<span className={`chip ${person.revealed ? 'chip-success' : ''}`} title={person.revealed ? 'Já viu quem tirou' : 'Ainda não viu quem tirou'}>
									{person.revealed ? <Icon name="check" className="size-3.5" /> : '…'}
									<span className="hidden sm:inline">{person.revealed ? 'viu' : 'pendente'}</span>
								</span>
							)}

							<a href={person.listUrl} className="btn btn-soft btn-sm">
								<Icon name="gift" className="size-4" />
								<span className="hidden sm:inline">Lista</span>
							</a>

							{group.isManager && !group.isDrawn && (
								<button type="button" className="btn-icon hover:text-berry" onClick={() => removeParticipant(person)} disabled={busy === person.id} aria-label={`Remover ${person.name}`}>
									{busy === person.id ? <Spinner /> : <span aria-hidden="true" className="text-xl leading-none">×</span>}
								</button>
							)}
						</li>
					))}
				</ul>
			)}
		</section>
	);
}

function AddParticipant({ group, onChange }) {
	const [name, setName] = useState('');
	const [email, setEmail] = useState('');
	const [status, setStatus] = useState({ type: '', message: '' });
	const [saving, setSaving] = useState(false);

	if (group.isDrawn) return null;

	async function submit(event) {
		event.preventDefault();
		setSaving(true);
		setStatus({ type: '', message: '' });
		try {
			onChange(await api(`groups/${group.id}/participants`, { method: 'POST', body: { name, email } }));
			setStatus({ type: 'success', message: `Convite enviado para ${email}.` });
			setName('');
			setEmail('');
		} catch (err) {
			setStatus({ type: 'error', message: err.message });
		} finally {
			setSaving(false);
		}
	}

	return (
		<section className="card p-6">
			<h2 className="flex items-center gap-2 text-lg font-bold">
				<Icon name="mail" className="size-5 text-grape" />
				Adicionar participante
			</h2>
			<p className="mt-1 text-sm text-ink/60">A pessoa recebe um e-mail para criar a senha e montar a lista.</p>

			<form onSubmit={submit} className="mt-5 space-y-3">
				<label className="field">
					<span className="field-label">Nome</span>
					<input className="input" value={name} onChange={(e) => setName(e.target.value)} placeholder="Como aparece no grupo" />
				</label>
				<label className="field">
					<span className="field-label">E-mail *</span>
					<input className="input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="pessoa@email.com" required />
				</label>
				<button type="submit" className="btn btn-primary w-full" disabled={saving}>
					{saving && <Spinner />}
					Adicionar
				</button>
				<Notice type={status.type}>{status.message}</Notice>
			</form>
		</section>
	);
}

function DrawPanel({ group, onChange }) {
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState('');
	const count = group.participants.length;
	const missing = Math.max(0, group.minParticipants - count);
	const revealed = group.participants.filter((p) => p.revealed).length;

	async function run(method, confirmText) {
		if (!window.confirm(confirmText)) return;
		setBusy(true);
		setError('');
		try {
			onChange(await api(`groups/${group.id}/draw`, { method }));
		} catch (err) {
			setError(err.message);
		} finally {
			setBusy(false);
		}
	}

	if (group.isDrawn) {
		return (
			<section className="card p-6">
				<h2 className="flex items-center gap-2 text-lg font-bold">
					<Icon name="check" className="size-5 text-mint" />
					Sorteio realizado
				</h2>
				<p className="mt-1 text-sm text-ink/60">
					{revealed} de {count} já {revealed === 1 ? 'viu' : 'viram'} quem tirou. Só cada participante sabe o próprio resultado.
				</p>
				<div className="mt-4 h-2 overflow-hidden rounded-full bg-ink/5">
					<div className="h-full rounded-full bg-mint transition-all" style={{ width: `${count ? (revealed / count) * 100 : 0}%` }} />
				</div>
				<button
					type="button"
					className="btn btn-danger btn-sm mt-5 w-full"
					disabled={busy}
					onClick={() => run('DELETE', 'Desfazer o sorteio? Os resultados atuais serão apagados e você poderá alterar participantes e sortear de novo.')}
				>
					{busy && <Spinner />}
					Desfazer sorteio
				</button>
				<div className="mt-3">
					<Notice type="error">{error}</Notice>
				</div>
			</section>
		);
	}

	return (
		<section className="card p-6">
			<h2 className="flex items-center gap-2 text-lg font-bold">
				<Icon name="shuffle" className="size-5 text-grape" />
				Sorteio
			</h2>
			<p className="mt-1 text-sm text-ink/60">
				{missing > 0
					? `Adicione mais ${missing} ${missing === 1 ? 'participante' : 'participantes'} para liberar o sorteio.`
					: 'Tudo pronto! Ninguém tira a si mesmo e cada pessoa recebe o resultado por e-mail.'}
			</p>
			<button
				type="button"
				className="btn btn-accent mt-5 w-full"
				disabled={busy || missing > 0}
				onClick={() => run('POST', `Sortear entre ${count} participantes agora? Depois do sorteio não dá para alterar participantes sem desfazê-lo.`)}
			>
				{busy ? <Spinner /> : <Icon name="sparkles" className="size-4" />}
				Fazer sorteio
			</button>
			<div className="mt-3">
				<Notice type="error">{error}</Notice>
			</div>
		</section>
	);
}
