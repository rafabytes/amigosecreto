import { useEffect, useRef, useState } from 'react';
import { config } from '../lib/api';

/** Ícone SVG — mesmos caminhos de inc/icons.php (injetados em window.AmigoSecreto.icons). */
export function Icon({ name, className = 'size-5' }) {
	const d = config.icons?.[name];
	if (!d) return null;
	return (
		<svg className={className} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.75} stroke="currentColor" aria-hidden="true">
			<path strokeLinecap="round" strokeLinejoin="round" d={d} />
		</svg>
	);
}

/** Campo de senha com botão "mostrar senha" (mesmo visual de as_password_toggle() no PHP). */
export function PasswordInput({ className = '', ...props }) {
	const [visible, setVisible] = useState(false);
	const label = visible ? 'Ocultar senha' : 'Mostrar senha';

	return (
		<span className="relative block">
			<input {...props} type={visible ? 'text' : 'password'} className={`input pr-12 ${className}`} />
			<button type="button" className="password-toggle" aria-pressed={visible} aria-label={label} title={label} onClick={() => setVisible((v) => !v)}>
				<Icon name={visible ? 'eye-off' : 'eye'} />
			</button>
		</span>
	);
}

export function Avatar({ user, className = 'size-10 text-sm' }) {
	if (user.avatar) {
		return <img src={user.avatar} alt="" title={user.name} className={`avatar object-cover ${className}`} loading="lazy" />;
	}
	return (
		<span className={`avatar ${className}`} title={user.name}>
			{user.initials}
		</span>
	);
}

export function Spinner({ className = 'size-4' }) {
	return <span className={`${className} inline-block animate-spin rounded-full border-2 border-current border-r-transparent`} aria-hidden="true" />;
}

export function Notice({ type, children }) {
	if (!children) return null;
	return (
		<div className={`notice ${type ? `notice-${type}` : ''}`} role={type === 'error' ? 'alert' : 'status'}>
			{children}
		</div>
	);
}

/** Modal baseado em <dialog>: foco, Esc e backdrop nativos. */
export function Modal({ open, onClose, title, children }) {
	const ref = useRef(null);

	useEffect(() => {
		const dialog = ref.current;
		if (!dialog) return;
		if (open && !dialog.open) dialog.showModal();
		if (!open && dialog.open) dialog.close();
	}, [open]);

	return (
		<dialog
			ref={ref}
			className="modal"
			onClose={onClose}
			onClick={(event) => event.target === ref.current && onClose()}
		>
			<div className="p-6 sm:p-8">
				<div className="mb-6 flex items-start justify-between gap-4">
					<h2 className="text-xl font-bold">{title}</h2>
					<button type="button" className="btn-icon -mr-2 -mt-1" onClick={onClose} aria-label="Fechar">
						<span aria-hidden="true" className="text-xl leading-none">×</span>
					</button>
				</div>
				{open && children}
			</div>
		</dialog>
	);
}
