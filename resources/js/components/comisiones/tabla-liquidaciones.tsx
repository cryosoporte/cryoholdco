import { Link } from "@inertiajs/react";
import { ArrowUpRightIcon } from "lucide-react";
import { route } from "ziggy-js";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatearFechaHora } from "@/lib/fechas";
import { formatearSoles } from "./formato";
import { InsigniaEstado } from "./insignia-estado";
import type { ResumenLiquidacion } from "./tipos";

export function TablaLiquidaciones({ liquidaciones }: { liquidaciones: ResumenLiquidacion[] }) {
    return (
        <div className="min-w-0 overflow-hidden rounded-xl border bg-card">
            <Table className="[&_td]:px-4 [&_th]:px-4">
                <TableHeader className="bg-muted/40">
                    <TableRow>
                        <TableHead scope="col">Período</TableHead>
                        <TableHead scope="col">Tipo</TableHead>
                        <TableHead scope="col">Estado</TableHead>
                        <TableHead scope="col" className="text-right">Total</TableHead>
                        <TableHead scope="col" className="text-right">Con comisión</TableHead>
                        <TableHead scope="col">Actualizada</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {liquidaciones.map((liquidacion) => (
                        <TableRow key={liquidacion.id}>
                            <TableCell>
                                <Link
                                    href={route("reportes.comisiones.liquidaciones.mostrar", liquidacion.id)}
                                    className="inline-flex items-center gap-1.5 rounded-sm py-1 font-semibold text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    {liquidacion.periodo}
                                    <ArrowUpRightIcon className="size-3.5" aria-hidden="true" />
                                </Link>
                            </TableCell>
                            <TableCell className="capitalize text-muted-foreground">{liquidacion.tipo_periodo}</TableCell>
                            <TableCell><InsigniaEstado estado={liquidacion.estado} /></TableCell>
                            <TableCell className="text-right font-semibold tabular-nums">{liquidacion.total === null ? "—" : formatearSoles(liquidacion.total)}</TableCell>
                            <TableCell className="text-right tabular-nums">{liquidacion.colaboradores === null ? "—" : `${liquidacion.colaboradores_con_comision} de ${liquidacion.colaboradores}`}</TableCell>
                            <TableCell className="text-muted-foreground tabular-nums">{formatearFechaHora(liquidacion.actualizada_en)}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
