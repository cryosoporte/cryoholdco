import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { formatearFechaHora } from "@/lib/fechas";
import { FormularioCargaFuente } from "./formulario-carga-fuente";
import type { CargaFuente, FuenteDatos } from "./tipos";

function textoUltimaCarga(carga: CargaFuente): string {
    switch (carga.estado) {
        case "P":
            return "Carga recibida. En espera de procesamiento.";
        case "E":
            return "Procesando…";
        case "C":
            return carga.filas === null
                ? "Completada"
                : `Completada (${carga.filas.toLocaleString("es-PE")} filas)`;
        case "F":
            return carga.mensaje ? `Falló: ${carga.mensaje}` : "Falló la carga.";
    }
}

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
            <CardContent className="space-y-4">
                <p
                    role="status"
                    className={
                        fuente.ultima_carga?.estado === "F"
                            ? "text-sm text-destructive"
                            : "text-sm text-muted-foreground"
                    }
                    hidden={!fuente.ultima_carga}
                >
                    {fuente.ultima_carga && textoUltimaCarga(fuente.ultima_carga)}
                </p>
                <FormularioCargaFuente fuente={fuente} />
            </CardContent>
        </Card>
    );
}
