import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { formatearFechaHora } from "@/lib/fechas";
import { FormularioCargaFuente } from "./formulario-carga-fuente";
import type { FuenteDatos } from "./tipos";

/** Una fuente con su última carga y el formulario para actualizarla. */
export function TarjetaFuente({ fuente }: { fuente: FuenteDatos }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{fuente.nombre}</CardTitle>
                <CardDescription>
                    {fuente.fecha_actualizacion ? (
                        <>
                            Actualizada el{" "}
                            {formatearFechaHora(fuente.fecha_actualizacion)}
                            {fuente.actualizado_por &&
                                ` por ${fuente.actualizado_por}`}
                            {fuente.filas !== null &&
                                ` · ${fuente.filas.toLocaleString("es-PE")} filas`}
                            {fuente.archivo && ` · ${fuente.archivo}`}
                        </>
                    ) : (
                        "Sin datos cargados"
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <FormularioCargaFuente fuente={fuente} />
            </CardContent>
        </Card>
    );
}
