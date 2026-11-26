import { useRef } from "react";
import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { Button } from "@/components/ui/button";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import { hayCargaActiva, type FuenteDatos } from "./tipos";

/**
 * Sube el .xlsx de una fuente para reemplazar sus datos.
 */
export function FormularioCargaFuente({ fuente }: { fuente: FuenteDatos }) {
    const formulario = useForm<{ archivo: File | null }>({ archivo: null });
    const inputArchivo = useRef<HTMLInputElement>(null);
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const idCampo = `archivo-${fuente.codigo}`;
    const deshabilitado = formulario.processing || hayCargaActiva(fuente);

    function cargar(): void {
        if (deshabilitado) {
            return;
        }

        formulario.post(
            route("reportes.fuentes.cargar", fuente.codigo.toLowerCase()),
            {
                ...opcionesPeticion,
                preserveScroll: true,
                onFlash: (flash) => {
                    if (flash.cargaFuenteRecibida) {
                        toast.success("Carga recibida", {
                            description: flash.cargaFuenteRecibida,
                        });
                    }

                    if (flash.fuenteCargada) {
                        toast.success("Fuente actualizada", {
                            description: `${flash.fuenteCargada.filas.toLocaleString("es-PE")} filas en ${flash.fuenteCargada.fuente}`,
                        });
                    }
                },
                onSuccess: () => {
                    formulario.reset();
                    if (inputArchivo.current) {
                        inputArchivo.current.value = "";
                    }
                },
            },
        );
    }

    return (
        <form
            onSubmit={(evento) => {
                evento.preventDefault();
                cargar();
            }}
            className="grid gap-4"
            aria-busy={formulario.processing}
        >
            <FormError message={errorPeticion} />

            <Field data-invalid={!!formulario.errors.archivo}>
                <FieldLabel htmlFor={idCampo}>Archivo .xlsx</FieldLabel>
                <Input
                    ref={inputArchivo}
                    id={idCampo}
                    type="file"
                    accept=".xlsx"
                    onChange={(evento) =>
                        formulario.setData(
                            "archivo",
                            evento.target.files?.[0] ?? null,
                        )
                    }
                    disabled={deshabilitado}
                    aria-invalid={!!formulario.errors.archivo}
                    aria-describedby={`ayuda-${idCampo} error-${idCampo}`}
                />
                <FieldDescription id={`ayuda-${idCampo}`}>
                    Reemplaza todos los datos de la fuente. Si el archivo
                    tiene algún error, no se cambia nada.
                </FieldDescription>
                <FieldError id={`error-${idCampo}`}>
                    {formulario.errors.archivo}
                </FieldError>
            </Field>

            <Button
                type="submit"
                disabled={
                    deshabilitado || formulario.data.archivo === null
                }
                className="w-fit cursor-pointer"
            >
                {formulario.processing && <Spinner />}
                {formulario.processing ? "Cargando…" : "Cargar"}
            </Button>
        </form>
    );
}
