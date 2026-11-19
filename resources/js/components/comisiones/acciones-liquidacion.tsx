import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { CornerDownLeftIcon } from "lucide-react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatearFechaHora } from "@/lib/fechas";
import { DialogoRechazo } from "./dialogo-rechazo";
import { formatearSoles } from "./formato";
import type { EventoLiquidacion, Liquidacion, PermisosDemo, RolDemo } from "./tipos";
import { useAccionComisiones } from "./use-accion-comisiones";

function ultimoEvento(liquidacion: Liquidacion, tipo: EventoLiquidacion["tipo"]) {
    return [...liquidacion.historial].reverse().find((evento) => evento.tipo === tipo);
}

export function AccionesLiquidacion({ liquidacion, rol, permisos }: {
    liquidacion: Liquidacion;
    rol: RolDemo;
    permisos: PermisosDemo;
}) {
    const { estado, es_demo_activa: activa } = liquidacion;

    if (estado === "aprobada" || estado === "enviada_personal") {
        const aprobada = ultimoEvento(liquidacion, "aprobada");

        return (
            <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                {aprobada
                    ? <>Período cerrado, aprobado por <strong>{aprobada.por}</strong> el {formatearFechaHora(aprobada.fecha, " a las ")}.</>
                    : "Período cerrado y aprobado por gerencia."}{" "}
                Cualquier corrección se registrará como ajuste.
            </p>
        );
    }

    if (!activa) {
        return <p className="text-sm text-muted-foreground">Genera una liquidación para este período desde el historial para empezar la revisión.</p>;
    }

    if (estado === "enviada") {
        if (permisos.puede_aprobar) {
            return <RevisionGerencia liquidacion={liquidacion} puedeRechazar={permisos.puede_rechazar} />;
        }

        const procesada = ultimoEvento(liquidacion, "procesada");

        return (
            <Card>
                <CardContent className="flex flex-wrap items-center justify-between gap-3">
                    <p>Enviada a gerencia{procesada && <> el {formatearFechaHora(procesada.fecha, " a las ")}</>}. Esperando su aprobación.</p>
                    <a className="font-semibold text-primary underline underline-offset-4"
                        href={route("reportes.comisiones.liquidaciones.correo-gerencia", liquidacion.id)}
                        target="_blank" rel="noreferrer">
                        Ver correo enviado
                    </a>
                </CardContent>
            </Card>
        );
    }

    if (estado === "rechazada") {
        const rechazo = ultimoEvento(liquidacion, "rechazada");

        if (rol === "gerenta") {
            return (
                <p className="rounded-lg border bg-muted/40 px-4 py-3 text-sm">
                    Devolviste esta liquidación{rechazo && <> el {formatearFechaHora(rechazo.fecha, " a las ")}</>}.
                    {" "}Esperando que el analista la vuelva a procesar.
                </p>
            );
        }

        return (
            <div className="space-y-3">
                <Alert className="border-amber-600/30 bg-amber-500/10">
                    <CornerDownLeftIcon className="text-amber-800 dark:text-amber-300" aria-hidden="true" />
                    <AlertTitle>Liquidación devuelta por {rechazo?.por ?? "gerencia"}</AlertTitle>
                    <AlertDescription>
                        {rechazo && <time dateTime={rechazo.fecha}>{formatearFechaHora(rechazo.fecha, " a las ")}</time>}
                        {rechazo?.comentario && <p className="whitespace-pre-wrap wrap-break-word">{rechazo.comentario}</p>}
                    </AlertDescription>
                </Alert>
                {permisos.puede_procesar && <DialogoConfirmar liquidacion={liquidacion} accion="procesar" />}
            </div>
        );
    }

    return (
        <Card>
            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                {permisos.puede_procesar ? (
                    <>
                        <p className="max-w-2xl">
                            Revisa las tablas por cargo. Al procesar, se congela el cálculo y se envía un resumen a la gerencia para su aprobación.
                        </p>
                        <DialogoConfirmar liquidacion={liquidacion} accion="procesar" />
                    </>
                ) : <p>Aún no hay comisiones para aprobar. El analista debe procesar el período.</p>}
            </CardContent>
        </Card>
    );
}

function RevisionGerencia({ liquidacion, puedeRechazar }: {
    liquidacion: Liquidacion;
    puedeRechazar: boolean;
}) {
    return (
        <Card className="border border-primary/30 ring-0">
            <CardHeader><CardTitle><h2>Revisión de gerencia</h2></CardTitle></CardHeader>
            <CardContent className="space-y-4">
                <div className="grid min-w-0 gap-4 xl:grid-cols-2">
                    <div className="min-w-0 overflow-hidden rounded-lg border">
                        <Table aria-label="Resumen por cargo">
                            <TableHeader className="bg-muted/35">
                                <TableRow>
                                    <TableHead scope="col">Cargo</TableHead>
                                    <TableHead scope="col" className="text-right">Con comisión</TableHead>
                                    <TableHead scope="col" className="text-right">Total S/</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {liquidacion.resumen_cargos.map((cargo) => (
                                    <TableRow key={cargo.cargo}>
                                        <TableCell>{cargo.cargo}</TableCell>
                                        <TableCell className="text-right tabular-nums">{cargo.con_comision} de {cargo.colaboradores}</TableCell>
                                        <TableCell className="text-right tabular-nums">{formatearSoles(cargo.total)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell>Total</TableCell>
                                    <TableCell className="text-right tabular-nums">{liquidacion.colaboradores_con_comision} de {liquidacion.colaboradores}</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatearSoles(liquidacion.total)}</TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                    <div className="space-y-2 rounded-lg bg-muted/45 p-4">
                        <h3 className="font-semibold">Puntos a revisar</h3>
                        <ul className="list-disc space-y-2 pl-4 text-sm leading-relaxed text-muted-foreground">
                            {liquidacion.puntos_revision.map((punto) => <li key={punto}>{punto}</li>)}
                        </ul>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <DialogoConfirmar liquidacion={liquidacion} accion="aprobar" />
                    {puedeRechazar && <DialogoRechazo idLiquidacion={liquidacion.id} />}
                </div>
            </CardContent>
        </Card>
    );
}

function DialogoConfirmar({ liquidacion, accion }: { liquidacion: Liquidacion; accion: "procesar" | "aprobar" }) {
    const [abierto, setAbierto] = useState(false);
    const formulario = useForm<{ accion?: string }>({});
    const { errorPeticion, opciones } = useAccionComisiones();
    const aprobacion = accion === "aprobar";
    const titulo = aprobacion ? "Aprobar y enviar al personal" : "Procesar y enviar a Gerencia";
    const total = formatearSoles(liquidacion.total);

    return (
        <Dialog open={abierto} onOpenChange={(siguiente) => {
            if (!formulario.processing) {
                formulario.clearErrors();
                setAbierto(siguiente);
            }
        }}>
            <DialogTrigger render={<Button className="cursor-pointer" />}>{titulo}</DialogTrigger>
            <DialogContent>
                <form className="space-y-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    if (formulario.processing) {
                        return;
                    }

                    formulario.post(route(
                        aprobacion ? "reportes.comisiones.liquidaciones.aprobar" : "reportes.comisiones.liquidaciones.procesar",
                        liquidacion.id,
                    ), {
                        ...opciones,
                        onSuccess: () => setAbierto(false),
                    });
                }}>
                    <DialogHeader>
                        <DialogTitle>{titulo}</DialogTitle>
                        <DialogDescription>
                            {aprobacion
                                ? <>Se enviarán <strong>{liquidacion.asesores.length} correos</strong> individuales al aprobar el total de <strong>{total}</strong>.</>
                                : <>Se enviará un resumen de <strong>{total}</strong> a <strong>{liquidacion.correo_gerencia}</strong>.</>}
                        </DialogDescription>
                    </DialogHeader>
                    <FormError message={formulario.errors.accion ?? errorPeticion} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <DialogClose render={<Button type="button" variant="outline" disabled={formulario.processing} />}>
                            Cancelar
                        </DialogClose>
                        <Button type="submit" disabled={formulario.processing}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? (aprobacion ? "Enviando correos…" : "Enviando correo…") : titulo}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
