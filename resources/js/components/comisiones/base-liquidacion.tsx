import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { formatearFecha } from "@/lib/fechas";
import type { BaseLiquidacion as DatosBase } from "./tipos";

export function BaseLiquidacion({ base }: { base: DatosBase }) {
    return (
        <Card>
            <CardHeader><CardTitle><h2>Base de la liquidación</h2></CardTitle></CardHeader>
            <CardContent className="space-y-3">
                <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt className="text-xs text-muted-foreground">Fuente</dt>
                        <dd className="font-medium">{base.fuente}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Archivo</dt>
                        <dd className="font-medium wrap-break-word">{base.archivo}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Filas</dt>
                        <dd className="font-medium tabular-nums">{base.filas.toLocaleString("es-PE")}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Rango</dt>
                        <dd className="font-medium tabular-nums">{formatearFecha(base.desde)} al {formatearFecha(base.hasta)}</dd>
                    </div>
                </dl>
                <p className="border-t pt-3 text-xs text-muted-foreground">
                    Servicios sin configurar: <strong>{base.servicios_sin_configurar}</strong>
                    {" · "}
                    Nombres sin configurar: <strong>{base.nombres_sin_configurar}</strong>
                </p>
            </CardContent>
        </Card>
    );
}
