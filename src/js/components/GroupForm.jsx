import { useState } from 'react';
import { Notice, Spinner } from './ui';

const empty = { title: '', description: '', eventDate: '', location: '', budget: '', joinAsParticipant: true };

/**
 * Formulário de criação/edição de grupo. `onSubmit` recebe os valores e deve retornar uma Promise.
 */
export default function GroupForm({ initial = {}, submitLabel = 'Salvar', showJoin = true, joinDisabled = false, onSubmit, onCancel }) {
	const [values, setValues] = useState({ ...empty, ...initial });
	const [error, setError] = useState('');
	const [saving, setSaving] = useState(false);

	const set = (key) => (event) =>
		setValues((v) => ({ ...v, [key]: event.target.type === 'checkbox' ? event.target.checked : event.target.value }));

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
		<form onSubmit={handleSubmit} className="space-y-4">
			<Notice type="error">{error}</Notice>

			<label className="field">
				<span className="field-label">Nome do grupo *</span>
				<input className="input" value={values.title} onChange={set('title')} placeholder="Ex.: Natal da família 2026" required autoFocus />
			</label>

			<div className="grid gap-4 sm:grid-cols-2">
				<label className="field">
					<span className="field-label">Data da troca</span>
					<input className="input" type="date" value={values.eventDate} onChange={set('eventDate')} />
				</label>
				<label className="field">
					<span className="field-label">Valor sugerido</span>
					<input className="input" value={values.budget} onChange={set('budget')} placeholder="Ex.: até R$ 100" />
				</label>
			</div>

			<label className="field">
				<span className="field-label">Local</span>
				<input className="input" value={values.location} onChange={set('location')} placeholder="Ex.: Casa da vó" />
			</label>

			<label className="field">
				<span className="field-label">Recado para os participantes</span>
				<textarea className="input" value={values.description} onChange={set('description')} rows={3} placeholder="Regras, horário, dress code…" />
			</label>

			{showJoin && (
				<label className={`flex items-center gap-2 text-sm ${joinDisabled ? 'text-ink/40' : 'text-ink/80'}`}>
					<input type="checkbox" className="checkbox" checked={values.joinAsParticipant} onChange={set('joinAsParticipant')} disabled={joinDisabled} />
					Eu também participo do sorteio
				</label>
			)}

			<div className="flex justify-end gap-2 pt-2">
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
