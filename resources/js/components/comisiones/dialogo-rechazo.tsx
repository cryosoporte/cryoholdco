import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Field, FieldDescription, FieldError, FieldLabel } from "@/components/ui/field";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { useAccionComisiones } from "./use-accion-comisiones";

export function DialogoRechazo({ idLiquidacion, version }: { idLiquidacion: string; version: number | null }) {
    const [abierto, setAbierto] = useState(false);
    const formulario = useForm<{ comentario: string; liquidacion_id: number | null; accion?: string }>({ comentario: "", liquidacion_id: version });
    const { errorPeticion, opciones } = useAccionComisiones();

    function cambiarApertura(siguiente: boolean): void {
        if (formulario.processing) {
            return;
        }

        if (siguiente) {
            formulario.resetAndClearErrors();
        }

        setAbierto(siguiente);
    }

    return (
        <Dialog open={abierto} onOpenChange={cambiarApertura}>
            <DialogTrigger render={<Button variant="destructive" className="cursor-pointer" />}>
                Rechazar
            </DialogTrigger>
            <DialogContent>
                <form className="space-y-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    if (formulario.processing) {
                        return;
                    }

                    formulario.post(route("reportes.comisiones.liquidaciones.rechazar", idLiquidacion), {
                        ...opciones,
                        onSuccess: () => setAbierto(false),
                    });
                }}>
                    <DialogHeader>
                        <DialogTitle>Rechazar liquidación</DialogTitle>
                        <DialogDescription>Administración verá el comentario y podrá volver a procesar el período.</DialogDescription>
                    </DialogHeader>
                    <Field data-invalid={!!formulario.errors.comentario}>
                        <FieldLabel htmlFor="comentario-rechazo">Motivo del rechazo</FieldLabel>
                        <Textarea
                            id="comentario-rechazo"
                            value={formulario.data.comentario}
                            onChange={(evento) => formulario.setData("comentario", evento.target.value)}
                            required
                            minLength={10}
                            maxLength={500}
                            rows={4}
                            disabled={formulario.processing}
                            aria-invalid={!!formulario.errors.comentario}
                            aria-describedby="ayuda-rechazo error-rechazo"
                        />
                        <FieldDescription id="ayuda-rechazo">Entre 10 y 500 caracteres.</FieldDescription>
                        <FieldError id="error-rechazo">{formulario.errors.comentario}</FieldError>
                    </Field>
                    <FormError message={formulario.errors.accion ?? errorPeticion} />
                    <div className="flex flex-wrap justify-end gap-2">
                        <DialogClose render={<Button type="button" variant="outline" disabled={formulario.processing} />}>
                            Cancelar
                        </DialogClose>
                        <Button type="submit" variant="destructive" disabled={formulario.processing}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? "Devolviendo…" : "Devolver liquidación"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
