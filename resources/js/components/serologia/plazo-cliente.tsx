import type { Plazo } from "./tipos";
import { Progress } from "@/components/ui/progress";

export function PlazoCliente({ plazo }: { plazo: Plazo | null }) {
    if (plazo === null) return <span className="text-muted-foreground">—</span>;

    return (
        <div className="min-w-44 space-y-2 py-1">
            {plazo.porcentaje_transcurrido !== null && (
                <Progress
                    value={plazo.porcentaje_transcurrido}
                    aria-label="Plazo para la toma de muestra"
                    aria-valuetext={plazo.texto}
                    className="w-44"
                />
            )}
            <p className="text-xs text-muted-foreground">{plazo.texto}</p>
        </div>
    );
}
