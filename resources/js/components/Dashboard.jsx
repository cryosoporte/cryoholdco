import { useState } from 'react';

export default function Dashboard({ usuario = null }) {
    const [contador, setContador] = useState(0);

    return (
        <section className="mt-6 rounded-xl border border-slate-200 bg-white p-6">
            <h3 className="text-lg font-semibold text-slate-900">Dashboard</h3>

            <p className="mt-1 text-sm text-slate-500">
                Componente React montado desde <code>resources/js/app.jsx</code>
                {usuario ? ` para ${usuario}` : ''}.
            </p>

            <button
                type="button"
                onClick={() => setContador((n) => n + 1)}
                className="mt-4 rounded-lg bg-sky-700 px-3 py-2 text-sm font-medium text-white hover:bg-sky-800"
            >
                Interacciones: {contador}
            </button>
        </section>
    );
}
