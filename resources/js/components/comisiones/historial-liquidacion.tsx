import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatearFechaHora } from "@/lib/fechas";
import { eventos } from "./configuracion";
import type { EventoLiquidacion } from "./tipos";

export function HistorialLiquidacion({ historial }: { historial: EventoLiquidacion[] }) {
    return (
        <Card>
            <CardHeader><CardTitle><h2>Historial de la liquidación</h2></CardTitle></CardHeader>
            <CardContent>
                {historial.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Aún no hay eventos registrados.</p>
                ) : (
                    <ol className="divide-y">
                        {historial.map((evento, indice) => (
                            <li key={`${evento.tipo}-${indice}`} className="space-y-1 py-3 first:pt-0 last:pb-0">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <p className="font-medium">{eventos[evento.tipo]}</p>
                                    <time className="text-sm text-muted-foreground tabular-nums" dateTime={evento.fecha}>{formatearFechaHora(evento.fecha)}</time>
                                </div>
                                <p className="text-sm text-muted-foreground">{evento.por}</p>
                                {evento.comentario && <p className="mt-2 whitespace-pre-wrap rounded-md bg-muted/50 p-2.5 wrap-break-word">{evento.comentario}</p>}
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
