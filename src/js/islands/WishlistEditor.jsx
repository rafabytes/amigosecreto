import { useState } from 'react';
import { Icon, Notice, Spinner } from '../components/ui';
import { api, config } from '../lib/api';

const emptyGift = { title: '', url: '', price: '', note: '' };

/** Editor da lista de presentes do usuário logado. */
export default function WishlistEditor({ initialGifts = [] }) {
	const [gifts, setGifts] = useState(initialGifts);
	const [editingId, setEditingId] = useState(null);
	const [error, setError] = useState('');

	async function create(values) {
		const gift = await api('wishlist', { method: 'POST', body: values });
		setGifts((list) => [...list, gift]);
	}

	async function update(id, values) {
		const gift = await api(`wishlist/${id}`, { method: 'POST', body: values });
		setGifts((list) => list.map((g) => (g.id === id ? gift : g)));
		setEditingId(null);
	}

	async function remove(gift) {
		if (!window.confirm(`Remover "${gift.title}" da lista?`)) return;
		setError('');
		try {
			await api(`wishlist/${gift.id}`, { method: 'DELETE' });
			setGifts((list) => list.filter((g) => g.id !== gift.id));
		} catch (err) {
			setError(err.message);
		}
	}

	async function move(index, direction) {
		const next = [...gifts];
		const target = index + direction;
		[next[index], next[target]] = [next[target], next[index]];
		setGifts(next);
		setError('');
		try {
			await api('wishlist/order', { method: 'POST', body: { ids: next.map((g) => g.id) } });
		} catch (err) {
			setGifts(gifts);
			setError(err.message);
		}
	}

	return (
		<div className="space-y-6">
			<GiftForm key={gifts.length} onSubmit={create} submitLabel="Adicionar à lista" />

			<Notice type="error">{error}</Notice>

			{gifts.length > 0 ? (
				<>
					<div className="flex items-center justify-between gap-4">
						<h2 className="text-lg font-bold">
							{gifts.length} {gifts.length === 1 ? 'item' : 'itens'} na lista
						</h2>
						{config.routes.myPublicList && (
							<a href={config.routes.myPublicList} className="text-sm font-medium text-grape hover:underline">
								Ver como os outros veem
							</a>
						)}
					</div>
					<ol className="space-y-3">
						{gifts.map((gift, index) => (
							<li key={gift.id} className="card p-5">
								{editingId === gift.id ? (
									<GiftForm initial={gift} submitLabel="Salvar" onSubmit={(values) => update(gift.id, values)} onCancel={() => setEditingId(null)} bare />
								) : (
									<GiftRow
										gift={gift}
										position={index + 1}
										canUp={index > 0}
										canDown={index < gifts.length - 1}
										onUp={() => move(index, -1)}
										onDown={() => move(index, 1)}
										onEdit={() => setEditingId(gift.id)}
										onDelete={() => remove(gift)}
									/>
								)}
							</li>
						))}
					</ol>
				</>
			) : (
				<div className="card p-10 text-center">
					<p className="text-5xl" aria-hidden="true">🎁</p>
					<p className="mt-4 font-semibold">Sua lista está vazia</p>
					<p className="mt-1 text-ink/60">Comece com 3 a 5 ideias de preços variados — fica mais fácil para quem tirar você.</p>
				</div>
			)}
		</div>
	);
}

function GiftRow({ gift, position, canUp, canDown, onUp, onDown, onEdit, onDelete }) {
	return (
		<div className="flex gap-4">
			<div className="flex flex-col items-center gap-0.5">
				<button type="button" className="btn-icon size-7" onClick={onUp} disabled={!canUp} aria-label="Mover para cima">
					<span aria-hidden="true">▲</span>
				</button>
				<span className="text-sm font-bold text-grape">{position}</span>
				<button type="button" className="btn-icon size-7" onClick={onDown} disabled={!canDown} aria-label="Mover para baixo">
					<span aria-hidden="true">▼</span>
				</button>
			</div>

			<div className="min-w-0 flex-1">
				<div className="flex flex-wrap items-center gap-2">
					<h3 className="text-lg font-bold">{gift.title}</h3>
					{gift.price && <span className="chip">{gift.price}</span>}
				</div>
				{gift.note && <p className="mt-1 whitespace-pre-line text-ink/70">{gift.note}</p>}
				{gift.url && (
					<a href={gift.url} target="_blank" rel="noopener noreferrer nofollow" className="mt-2 inline-flex max-w-full items-center gap-1.5 text-sm font-medium text-grape hover:underline" data-no-swup>
						<Icon name="link" className="size-4 shrink-0" />
						<span className="truncate">{gift.host || gift.url}</span>
					</a>
				)}
			</div>

			<div className="flex shrink-0 flex-col gap-1 sm:flex-row sm:items-start">
				<button type="button" className="btn btn-ghost btn-sm" onClick={onEdit}>
					Editar
				</button>
				<button type="button" className="btn btn-sm text-ink/50 hover:text-berry" onClick={onDelete}>
					Remover
				</button>
			</div>
		</div>
	);
}

function GiftForm({ initial = emptyGift, submitLabel, onSubmit, onCancel, bare = false }) {
	const [values, setValues] = useState({ title: initial.title, url: initial.url, price: initial.price, note: initial.note });
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');

	const set = (key) => (event) => setValues((v) => ({ ...v, [key]: event.target.value }));

	async function handleSubmit(event) {
		event.preventDefault();
		setSaving(true);
		setError('');
		try {
			await onSubmit(values);
		} catch (err) {
			setError(err.message);
			setSaving(false);
		}
	}

	return (
		<form onSubmit={handleSubmit} className={bare ? 'space-y-4' : 'card space-y-4 p-6'}>
			{!bare && (
				<h2 className="flex items-center gap-2 text-lg font-bold">
					<Icon name="gift" className="size-5 text-grape" />
					Nova ideia de presente
				</h2>
			)}

			<Notice type="error">{error}</Notice>

			<div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
				<label className="field">
					<span className="field-label">O que você quer ganhar? *</span>
					<input className="input" value={values.title} onChange={set('title')} placeholder="Ex.: Fone de ouvido bluetooth" required />
				</label>
				<label className="field">
					<span className="field-label">Preço aproximado</span>
					<input className="input" value={values.price} onChange={set('price')} placeholder="R$ 150" />
				</label>
			</div>

			<label className="field">
				<span className="field-label">Link da loja</span>
				<input className="input" type="text" inputMode="url" value={values.url} onChange={set('url')} placeholder="https://…" />
			</label>

			<label className="field">
				<span className="field-label">Observações</span>
				<textarea className="input" rows={2} value={values.note} onChange={set('note')} placeholder="Cor, tamanho, modelo, qualquer detalhe que ajude" />
			</label>

			<div className="flex justify-end gap-2">
				{onCancel && (
					<button type="button" className="btn btn-ghost" onClick={onCancel}>
						Cancelar
					</button>
				)}
				<button type="submit" className="btn btn-primary" disabled={saving}>
					{saving && <Spinner />}
					{submitLabel}
				</button>
			</div>
		</form>
	);
}
