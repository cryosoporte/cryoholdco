import { useRef, useState } from "react";
import { useHttp } from "@inertiajs/react";
import { HttpResponseError } from "@inertiajs/core";
import { route } from "ziggy-js";
import { toast } from "@/components/ui/sonner";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import { EstadoCliente } from "./estado-cliente";
import { PlazoCliente } from "./plazo-cliente";
import type { Resultado } from "./tipos";

type TablaResultadosProps = {
    resultados: Resultado[];
    actualizando: boolean;
    onSeleccionarCliente: (cliente: Resultado) => void;
};

export function TablaResultados({
    resultados,
    actualizando,
    onSeleccionarCliente,
}: TablaResultadosProps) {
    const recordatorio = useHttp<
        { numero_base: string; identificacion: string },
        { message: string }
    >({ numero_base: "", identificacion: "" });
    const envioEnCurso = useRef(false);
    const [clienteEnEnvio, setClienteEnEnvio] = useState<string | null>(null);

    async function enviarRecordatorio(cliente: Resultado): Promise<void> {
        if (envioEnCurso.current || actualizando || !cliente.numero_base) {
            return;
        }

        envioEnCurso.current = true;
        setClienteEnEnvio(
            JSON.stringify([cliente.numero_base, cliente.identificacion]),
        );
        recordatorio.transform(() => ({
            numero_base: cliente.numero_base ?? "",
            identificacion: cliente.identificacion,
        }));

        try {
            await recordatorio.post(route("serologia.recordatorio"), {
                onSuccess: (respuesta) => {
                    toast.success(respuesta.message, {
                        description: cliente.nombre,
                    });
                },
                onError: (errores) => {
                    toast.error(
                        Object.values(errores)[0] ??
                            "No se pudo enviar el recordatorio.",
                    );
                },
            });
        } catch (error) {
            toast.error(
                error instanceof HttpResponseError &&
                    [401, 419].includes(error.response.status)
                    ? "Tu sesión caducó. Recarga la página e intenta de nuevo."
                    : "No se pudo confirmar el envío del recordatorio. Revisa la conexión e intenta de nuevo más tarde.",
            );
        } finally {
            envioEnCurso.current = false;
            setClienteEnEnvio(null);
        }
    }

    return (
        <Table aria-busy={actualizando}>
            <TableHeader>
                <TableRow>
                    <TableHead>Cliente</TableHead>
                    <TableHead>Asesor</TableHead>
                    <TableHead>Plazo</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {resultados.map((fila) => {
                    return (
                        <TableRow
                            key={JSON.stringify([
                                fila.no_cia,
                                fila.numero_base,
                                fila.identificacion,
                                fila.nombre,
                                fila.serologia,
                                fila.numero_toma,
                                fila.fecha_toma,
                                fila.fecha_resultado,
                                fila.asesor_dni,
                                fila.asesor_nombre,
                                fila.fecha_nacimiento,
                            ])}
                        >
                            <TableCell>
                                <Button
                                    variant="link"
                                    type="button"
                                    onClick={() => onSeleccionarCliente(fila)}
                                    className="h-auto justify-start whitespace-normal p-0 text-left font-semibold text-foreground hover:text-criocord-600 transition-all"
                                >
                                    {fila.nombre}
                                </Button>
                                <p className="flex gap-2 text-xs text-muted-foreground">
                                    <span>{fila.identificacion}</span>
                                    <span>·</span>
                                    <span>{fila.numero_base ?? "-"}</span>
                                </p>
                            </TableCell>
                            <TableCell>
                                {fila.asesor_nombre ??
                                    fila.asesor_dni ??
                                    "Sin asesor"}
                            </TableCell>
                            <TableCell>
                                <PlazoCliente plazo={fila.plazo} />
                            </TableCell>
                            <TableCell>
                                <EstadoCliente fila={fila} />
                            </TableCell>
                            <TableCell>
                                <div className="flex flex-wrap justify-end gap-2">
                                    {fila.serologia === null &&
                                    fila.fecha_toma === null ? (
                                        <Button
                                            variant="outline"
                                            type="button"
                                            disabled={
                                                actualizando ||
                                                recordatorio.processing ||
                                                !fila.numero_base
                                            }
                                            title={
                                                !fila.numero_base
                                                    ? "El cliente no tiene un contrato base."
                                                    : undefined
                                            }
                                            onClick={() =>
                                                void enviarRecordatorio(fila)
                                            }
                                            className="cursor-pointer"
                                        >
                                            {clienteEnEnvio ===
                                            JSON.stringify([
                                                fila.numero_base,
                                                fila.identificacion,
                                            ])
                                                ? "Enviando…"
                                                : "Enviar recordatorio"}
                                        </Button>
                                    ) : fila.fecha_toma !== null &&
                                      fila.serologia === null ? (
                                        <Button
                                            variant="outline"
                                            disabled
                                            title="Disponible próximamente"
                                            className="cursor-pointer"
                                        >
                                            Resultado recibido
                                        </Button>
                                    ) : null}
                                    <Button
                                        variant="outline"
                                        type="button"
                                        className="cursor-pointer"
                                        aria-label={`Ver detalle de ${fila.nombre}`}
                                        onClick={() => onSeleccionarCliente(fila)}
                                    >
                                        Ver
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    );
                })}

                {resultados.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={5} className="py-6 text-center">
                            No se encontraron resultados.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
