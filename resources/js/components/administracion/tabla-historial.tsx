import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatearFechaHora } from "@/lib/fechas";
import type { EventoSeguridad } from "./tipos";

export function TablaHistorial({
    eventos,
    mensajeVacio,
}: {
    eventos: EventoSeguridad[];
    mensajeVacio: string;
}) {
    return (
        <Table aria-label="Historial de seguridad">
            <TableHeader>
                <TableRow>
                    <TableHead scope="col">Fecha</TableHead>
                    <TableHead scope="col">Autor</TableHead>
                    <TableHead scope="col">Acción</TableHead>
                    <TableHead scope="col">Detalle</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {eventos.map((evento) => (
                    <TableRow key={evento.id}>
                        <TableCell className="align-top tabular-nums text-muted-foreground">
                            <time dateTime={evento.fecha} title={evento.fecha}>{formatearFechaHora(evento.fecha)}</time>
                        </TableCell>
                        <TableCell className="whitespace-normal align-top">
                            {evento.autor}
                            {evento.empresa && <p className="text-xs text-muted-foreground">{evento.empresa}</p>}
                        </TableCell>
                        <TableCell className="min-w-44 whitespace-normal align-top">
                            <p className="font-medium">{evento.accion}</p>
                            {evento.referencia && (
                                <p className="wrap-break-word font-mono text-xs text-muted-foreground">{evento.referencia}</p>
                            )}
                        </TableCell>
                        <TableCell className="min-w-56 whitespace-normal align-top">
                            {evento.detalle.length === 0 ? "—" : (
                                <dl className="space-y-1 text-sm">
                                    {evento.detalle.map(({ etiqueta, valor }) => (
                                        <div key={etiqueta} className="grid grid-cols-[auto_1fr] gap-x-2">
                                            <dt className="font-medium">{etiqueta}</dt>
                                            <dd className="min-w-0 whitespace-pre-wrap wrap-break-word text-muted-foreground">{valor}</dd>
                                        </div>
                                    ))}
                                </dl>
                            )}
                        </TableCell>
                    </TableRow>
                ))}
                {eventos.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={4} className="py-6 text-center text-muted-foreground">{mensajeVacio}</TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
