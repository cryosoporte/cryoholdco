import { useState } from "react";
import { useForm } from "@inertiajs/react";
import { PlusIcon } from "lucide-react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import {
    Dialog, DialogClose, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger,
} from "@/components/ui/dialog";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { useAccionComisiones } from "./use-accion-comisiones";

export function DialogoNuevaLiquidacion({ periodos }: { periodos: { id: string; etiqueta: string }[] }) {
    const [abierto, setAbierto] = useState(false);
    const formulario = useForm<{ periodo: string | null; accion?: string }>({ periodo: null });
    const { errorPeticion, opciones } = useAccionComisiones();
    const items = periodos.map(({ id, etiqueta }) => ({ value: id, label: etiqueta }));

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
            <DialogTrigger render={<Button className="cursor-pointer" />}>
                <PlusIcon aria-hidden="true" />
                Nueva liquidación
            </DialogTrigger>
            <DialogContent>
                <form className="space-y-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    if (formulario.processing || formulario.data.periodo === null) {
                        return;
                    }

                    formulario.post(route("reportes.comisiones.liquidaciones.nueva"), {
                        ...opciones,
                        onSuccess: () => setAbierto(false),
                    });
                }}>
                    <DialogHeader>
                        <DialogTitle>Nueva liquidación</DialogTitle>
                        <DialogDescription>El período elegido reemplazará la liquidación activa de la demo.</DialogDescription>
                    </DialogHeader>
                    <Field data-invalid={!!formulario.errors.periodo}>
                        <FieldLabel id="etiqueta-periodo">Período</FieldLabel>
                        <Select
                            items={items}
                            value={formulario.data.periodo}
                            onValueChange={(valor) => formulario.setData("periodo", valor)}
                            disabled={formulario.processing}
                        >
                            <SelectTrigger className="w-full" aria-labelledby="etiqueta-periodo"
                                aria-invalid={!!formulario.errors.periodo} aria-describedby="error-periodo">
                                <SelectValue placeholder="Elige un período" />
                            </SelectTrigger>
                            <SelectContent>
                                {items.map(({ value, label }) => <SelectItem key={value} value={value}>{label}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <FieldError id="error-periodo">{formulario.errors.periodo}</FieldError>
                    </Field>
                    <FormError message={formulario.errors.accion ?? errorPeticion} />
                    <div className="flex justify-end gap-2">
                        <DialogClose render={<Button type="button" variant="outline" disabled={formulario.processing} />}>
                            Cancelar
                        </DialogClose>
                        <Button type="submit" disabled={formulario.processing || formulario.data.periodo === null}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? "Generando…" : "Generar liquidación"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
