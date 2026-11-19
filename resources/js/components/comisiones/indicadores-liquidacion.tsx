import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import type { Indicador } from "./tipos";

const tonos: Record<Indicador["tono"], string> = {
    neutral: "text-muted-foreground",
    positivo: "text-emerald-700 dark:text-emerald-300",
    atencion: "text-amber-800 dark:text-amber-300",
};

export function IndicadoresLiquidacion({ indicadores }: { indicadores: Indicador[] }) {
    return (
        <section aria-label="Indicadores de la liquidación" className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {indicadores.map(({ etiqueta, valor, detalle, tono }) => (
                <Card key={etiqueta}>
                    <CardContent>
                        <dl>
                            <dt className="text-sm font-medium text-muted-foreground">{etiqueta}</dt>
                            <dd className="mt-1 text-2xl font-semibold tracking-tight tabular-nums">{valor}</dd>
                            {detalle && <dd className={cn("mt-1 text-xs", tonos[tono])}>{detalle}</dd>}
                        </dl>
                    </CardContent>
                </Card>
            ))}
        </section>
    );
}
