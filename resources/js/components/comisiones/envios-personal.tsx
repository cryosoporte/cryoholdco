import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardAction, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatearFechaHora } from "@/lib/fechas";
import type { EnvioAsesor } from "./tipos";
import { useAccionComisiones } from "./use-accion-comisiones";

const estadosEnvio: Record<EnvioAsesor["estado"], { etiqueta: string; clase: string }> = {
    pendiente: { etiqueta: "Pendiente", clase: "bg-muted text-muted-foreground" },
    enviado: { etiqueta: "Enviado", clase: "border-emerald-600/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300" },
    fallido: { etiqueta: "Fallido", clase: "border-destructive/30 bg-destructive/10 text-destructive" },
};

export function EnviosPersonal({ idLiquidacion, envios, puedeReenviar }: {
    idLiquidacion: string;
    envios: EnvioAsesor[];
    puedeReenviar: boolean;
}) {
    const formulario = useForm<{ accion?: string }>({});
    const { errorPeticion, opciones } = useAccionComisiones();

    return (
        <Card aria-busy={formulario.processing}>
            <CardHeader className="has-data-[slot=card-action]:grid-cols-1 sm:has-data-[slot=card-action]:grid-cols-[1fr_auto]">
                <CardTitle><h2>Envíos al personal</h2></CardTitle>
                {puedeReenviar && (
                    <CardAction className="col-start-1 row-start-auto row-span-1 justify-self-start sm:col-start-2 sm:row-start-1">
                        <Button type="button" variant="outline" disabled={formulario.processing}
                            onClick={() => formulario.post(route("reportes.comisiones.liquidaciones.reenviar", idLiquidacion), opciones)}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? "Reenviando correos…" : "Reenviar fallidos"}
                        </Button>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent className="min-w-0 space-y-3">
                <FormError message={formulario.errors.accion ?? errorPeticion} />
                {envios.length === 0 ? (
                    <p className="text-muted-foreground">Los envíos aún no se han registrado.</p>
                ) : (
                    <div className="overflow-hidden rounded-lg border">
                        <Table>
                            <TableHeader className="bg-muted/40">
                                <TableRow>
                                    <TableHead scope="col">Asesor</TableHead>
                                    <TableHead scope="col">Correo</TableHead>
                                    <TableHead scope="col">Estado</TableHead>
                                    <TableHead scope="col">Fecha de envío</TableHead>
                                    <TableHead scope="col"><span className="sr-only">Vista previa</span></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {envios.map((envio) => (
                                    <TableRow key={envio.asesor_id}>
                                        <TableCell className="font-medium">{envio.nombre}</TableCell>
                                        <TableCell className="text-muted-foreground">{envio.correo}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline" className={estadosEnvio[envio.estado].clase}>
                                                {estadosEnvio[envio.estado].etiqueta}
                                            </Badge>
                                            {envio.error && <p className="mt-1 text-xs text-muted-foreground">{envio.error}</p>}
                                        </TableCell>
                                        <TableCell className="tabular-nums">{formatearFechaHora(envio.enviado_en)}</TableCell>
                                        <TableCell>
                                            <a className="font-medium text-primary underline underline-offset-4"
                                                aria-label={`Ver correo de ${envio.nombre}`}
                                                href={route("reportes.comisiones.liquidaciones.correo-asesor", { liquidacion: idLiquidacion, asesor: envio.asesor_id })}
                                                target="_blank" rel="noreferrer">
                                                Ver correo
                                            </a>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
