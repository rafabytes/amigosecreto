import { useState } from 'react';
import GroupForm from '../components/GroupForm';
import { Icon, Modal } from '../components/ui';
import { api, navigate } from '../lib/api';

/** Botão "Novo grupo" do painel (somente organizadores/admin). */
export default function CreateGroup() {
	const [open, setOpen] = useState(false);

	async function create(values) {
		const group = await api('groups', { method: 'POST', body: values });
		setOpen(false);
		navigate(group.url);
	}

	return (
		<>
			<button type="button" className="btn btn-primary" onClick={() => setOpen(true)}>
				<span aria-hidden="true" className="text-lg leading-none">+</span>
				Novo grupo
			</button>

			<Modal open={open} onClose={() => setOpen(false)} title="Novo amigo secreto">
				<p className="-mt-3 mb-6 flex items-center gap-2 text-sm text-ink/60">
					<Icon name="sparkles" className="size-4 text-grape" />
					Depois de criar, você adiciona os participantes pelo e-mail.
				</p>
				<GroupForm submitLabel="Criar grupo" onSubmit={create} onCancel={() => setOpen(false)} />
			</Modal>
		</>
	);
}
