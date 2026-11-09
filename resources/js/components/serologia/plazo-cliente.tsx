import type { Plazo } from "./tipos";

export function PlazoCliente({ plazo }: { plazo: Plazo | null }) {
    if (plazo === null) return <span className="text-muted-foreground">—</span>;

    return (
        <div className="min-w-44 space-y-2 py-1">
            {plazo.porcentaje_transcurrido !== null && (
                <div
                    role="progressbar"
                    aria-label="Plazo para la toma de muestra"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={plazo.porcentaje_transcurrido}
                    aria-valuetext={plazo.texto}
                    className="relative h-1.5 w-44 rounded-full bg-indigo-200 dark:bg-indigo-900"
                >
                    <div className="absolute inset-x-0.5 top-1/2">
                        <span
                            aria-hidden="true"
                            className="absolute h-4 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full bg-slate-800 dark:bg-slate-100"
                            style={{
                                left: `${plazo.porcentaje_transcurrido}%`,
                            }}
                        />
                    </div>
                </div>
            )}
            <p className="text-xs text-muted-foreground">{plazo.texto}</p>
        </div>
    );
}
