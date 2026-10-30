import { createRoot } from 'react-dom/client';
import CreateGroup from './CreateGroup';
import GroupPage from './GroupPage';
import ProfileForm from './ProfileForm';
import WishlistEditor from './WishlistEditor';

/** Componentes disponíveis para as_island() no PHP. */
const components = { CreateGroup, GroupPage, ProfileForm, WishlistEditor };

const roots = new Map();

export function mountIslands(scope = document) {
	scope.querySelectorAll('[data-island]').forEach((el) => {
		if (roots.has(el)) return;

		const Component = components[el.dataset.island];
		if (!Component) {
			console.warn(`[amigosecreto] Ilha desconhecida: ${el.dataset.island}`);
			return;
		}

		let props = {};
		try {
			props = JSON.parse(el.dataset.props || '{}');
		} catch (error) {
			console.error('[amigosecreto] data-props inválido', error);
		}

		const root = createRoot(el);
		root.render(<Component {...props} />);
		roots.set(el, root);
	});
}

export function unmountIslands() {
	roots.forEach((root) => root.unmount());
	roots.clear();
}
