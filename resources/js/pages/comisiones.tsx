import { useRef } from "react";
import { useForm } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";

import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { route } from "ziggy-js";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";

export default function Comisiones() {
    const formulario = useForm<{ archivo: File | null }>({ archivo: null });
    const inputArchivo = useRef<HTMLInputElement>(null);
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    const cargar = (): void => {
        if (formulario.processing) {
            return;
        }

        formulario.post(route("reportes.comisiones.cargar"), {
            ...opcionesPeticion,
            preserveScroll: true,
            onFlash: (flash) => {
                if (flash.carga) {
                    toast.success("Datos guardados", {
                        description: `${flash.carga.filas} filas de ${flash.carga.archivo}`,
                    });
                }
            },
            onSuccess: () => {
                formulario.reset();
                if (inputArchivo.current) {
                    inputArchivo.current.value = "";
                }
            },
        });
    };

    return (
        <AppLayout title="Reportes - Comisiones">
            <div className="space-y-6">
                <h1 className="text-2xl font-semibold">Comisiones</h1>

                <form
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        cargar();
                    }}
                    className="grid max-w-md gap-4"
                    aria-busy={formulario.processing}
                >
                    <FormError message={errorPeticion} />

                    <Field data-invalid={!!formulario.errors.archivo}>
                        <FieldLabel htmlFor="archivo">Archivo:</FieldLabel>
                        <Input
                            ref={inputArchivo}
                            id="archivo"
                            type="file"
                            accept=".csv"
                            onChange={(evento) =>
                                formulario.setData(
                                    "archivo",
                                    evento.target.files?.[0] ?? null,
                                )
                            }
                            disabled={formulario.processing}
                            aria-invalid={!!formulario.errors.archivo}
                        />
                        <FieldDescription>
                            Exportación de archivo para reporte de comisiones
                        </FieldDescription>
                        <FieldError>{formulario.errors.archivo}</FieldError>
                    </Field>

                    {formulario.data.archivo && (
                        <p className="text-sm">
                            Seleccionado: {formulario.data.archivo.name} (
                            {Math.ceil(formulario.data.archivo.size / 1024)} KB)
                        </p>
                    )}

                    <Button
                        type="submit"
                        disabled={
                            formulario.processing ||
                            formulario.data.archivo === null
                        }
                        className="cursor-pointer"
                    >
                        {formulario.processing && <Spinner />}
                        {formulario.processing ? "Cargando…" : "Cargar"}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
