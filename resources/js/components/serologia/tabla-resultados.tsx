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
                                fila.fecha_toma,
                                fila.fecha_resultado,
                                fila.asesor_dni,
                                fila.asesor_nombre,
                                fila.fecha_nacimiento,
                            ])}
                        >
                            <TableCell>
                                <button
                                    type="button"
                                    onClick={() => onSeleccionarCliente(fila)}
                                    className="cursor-pointer rounded-sm text-left font-bold transition-colors hover:text-indigo-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    {fila.nombre}
                                </button>
                                <p className="font-light flex gap-2 text-xs text-gray-500">
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
                            {/* <TableCell className="text-right">
                                {fila.serologia === null &&
                                    fila.fecha_toma === null && (
                                        <Button
                                            variant="outline"
                                            className="cursor-pointer"
                                        >
                                            Enviar recordatorio
                                        </Button>
                                    )}
                            </TableCell> */}
                            <TableCell>
                                {fila.serologia === null &&
                                fila.fecha_toma === null ? (
                                    <Button
                                        variant="outline"
                                        className="cursor-pointer"
                                    >
                                        Enviar recordatorio
                                    </Button>
                                ) : fila.fecha_toma !== null &&
                                  fila.serologia === null ? (
                                    <Button
                                        variant="outline"
                                        className="cursor-pointer"
                                    >
                                        Resultado recibido
                                    </Button>
                                ) : (
                                    <Button
                                        variant="outline"
                                        className="cursor-pointer"
                                        onClick={() =>
                                            onSeleccionarCliente(fila)
                                        }
                                    >
                                        Ver
                                    </Button>
                                )}
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
