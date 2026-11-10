import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import {
    CircleCheckIcon,
    CircleAlertIcon,
    LoaderCircleIcon,
} from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { FormError } from "@/components/form-error";
import {
    presentacionEstado,
    EstadoPruebaBadge,
} from "@/components/serologia/estado-prueba";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import { formatearFecha } from "@/lib/fechas";
import type { ResultadoLaboratorio } from "./tipos";

export function SemaforoToma({
    resultado,
    disabled,
    onProcesando,
}: {
    resultado: ResultadoLaboratorio;
    disabled: boolean;
    onProcesando: (valor: boolean) => void;
}) {
    const formulario = useForm({
        codigo: resultado.numero_contrato,
        numero_toma: resultado.numero_toma,
    });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const pendiente = resultado.estado === "pendiente";

    return (
        <Card>
            <CardContent className="space-y-4">
                <Alert
                    role="status"
                    className={
                        presentacionEstado[
                            resultado.recien_marcada
                                ? "recibido"
                                : resultado.estado
                        ].clase
                    }
                >
                    {resultado.recien_marcada ? (
                        <CircleCheckIcon />
                    ) : (
                        <CircleAlertIcon />
                    )}
                    <AlertTitle>
                        {resultado.nombre ?? "Clienta Criocord"}
                    </AlertTitle>
                    <AlertDescription>
                        <p className="font-semibold">
                            {resultado.recien_marcada
                                ? `Muestra tomada · ${resultado.hora_toma ?? ""}`
                                : pendiente
                                  ? "Puede tomarse"
                                  : "Ya fue tomada"}
                        </p>
                        <p>
                            {resultado.recien_marcada
                                ? "Listo. Criocord ya la ve en su tablero."
                                : pendiente
                                  ? `Verifica que el DNI físico sea de ${resultado.primer_nombre ?? "la clienta"}.`
                                  : `El ${formatearFecha(resultado.fecha_toma)} ${resultado.hora_toma ?? ""}${resultado.sede_toma ? ` en ${resultado.sede_toma}` : ""}. No repetir.`}
                        </p>
                    </AlertDescription>
                </Alert>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="font-mono text-xs">
                        {resultado.numero_contrato}
                        {resultado.numero_toma > 1
                            ? ` · toma ${resultado.numero_toma}`
                            : ""}
                    </span>
                    <EstadoPruebaBadge estado={resultado.estado} />
                </div>
                {pendiente && (
                    <form
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            if (disabled || formulario.processing) {
                                return;
                            }
                            formulario.transform(() => ({
                                codigo: resultado.numero_contrato,
                                numero_toma: resultado.numero_toma,
                            }));
                            formulario.post(route("laboratorio.marcar"), {
                                ...opcionesPeticion,
                                replace: true,
                                preserveScroll: true,
                                onStart: (visita) => {
                                    opcionesPeticion.onStart?.(visita);
                                    onProcesando(true);
                                },
                                onFinish: () => onProcesando(false),
                            });
                        }}
                    >
                        <Button
                            type="submit"
                            className="h-12 w-full"
                            disabled={disabled || formulario.processing}
                        >
                            {formulario.processing && (
                                <LoaderCircleIcon className="animate-spin" />
                            )}
                            Muestra tomada
                        </Button>
                    </form>
                )}
                <FormError message={formulario.errors.codigo} />
                <FormError message={errorPeticion} />
            </CardContent>
        </Card>
    );
}
