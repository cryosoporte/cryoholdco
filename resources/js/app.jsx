import { createRoot } from 'react-dom/client';
import Dashboard from './components/Dashboard';

/**
 * Registro de páginas React.
 *
 * Blade decide qué montar con data-pagina y le pasa el estado inicial en
 * data-props. Así cada vista nueva es una entrada en este objeto y un div
 * en la plantilla: las vistas que son solo Blade (login) siguen igual.
 */
const paginas = {
    dashboard: Dashboard,
};

document.querySelectorAll('[data-pagina]').forEach((contenedor) => {
    const Pagina = paginas[contenedor.dataset.pagina];

    if (!Pagina) {
        console.warn(`No hay componente registrado para la página "${contenedor.dataset.pagina}".`);

        return;
    }

    let props = {};

    try {
        props = contenedor.dataset.props ? JSON.parse(contenedor.dataset.props) : {};
    } catch (error) {
        console.error('data-props no es JSON válido.', error);
    }

    createRoot(contenedor).render(<Pagina {...props} />);
});
