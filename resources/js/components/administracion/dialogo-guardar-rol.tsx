import { useForm } from "@inertiajs/react";
import { useRef } from "react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Field, FieldError, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { Textarea } from "@/components/ui/textarea";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import type { RolResumen } from "./tipos";

export function DialogoGuardarRol({
    rol,
    onCerrar,
}: {
    rol: RolResumen | null;
    onCerrar: () => void;
}) {
    const inputNombre = useRef<HTMLInputElement>(null);
    const formulario = useForm({
        nombre: rol?.nombre ?? "",
        descripcion: rol?.descripcion ?? "",
    });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const puedeGuardar = formulario.isDirty && !formulario.processing;

    function guardar(): void {
        if (!puedeGuardar) {
            return;
        }

        const opciones = {
            ...opcionesPeticion,
            preserveScroll: rol !== null,
            onSuccess: () => {
                toast.success(rol ? "Rol actualizado" : "Rol creado", {
                    description: formulario.data.nombre,
                });
                onCerrar();
            },
        };

        if (rol) {
            formulario.put(route("administracion.roles.actualizar", rol.codigo), opciones);
        } else {
            formulario.post(route("administracion.roles.crear"), opciones);
        }
    }

    return (
        <Dialog open onOpenChange={(abierto) => {
            if (!abierto && !formulario.processing) onCerrar();
        }}>
            <DialogContent initialFocus={inputNombre}>
                <form className="grid gap-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    guardar();
                }}>
                    <DialogHeader>
                        <DialogTitle>{rol ? "Editar rol" : "Nuevo rol"}</DialogTitle>
                        <DialogDescription>
                            {rol ? `El código ${rol.codigo} se conserva.` : "El código se genera a partir del nombre al guardar."}
                        </DialogDescription>
                    </DialogHeader>
                    <FieldGroup>
                        <Field data-invalid={Boolean(formulario.errors.nombre)}>
                            <FieldLabel htmlFor="rol-nombre">Nombre</FieldLabel>
                            <Input
                                ref={inputNombre}
                                id="rol-nombre"
                                value={formulario.data.nombre}
                                onChange={(evento) => {
                                    formulario.setData("nombre", evento.target.value);
                                    formulario.clearErrors("nombre");
                                }}
                                required
                                minLength={3}
                                maxLength={60}
                                disabled={formulario.processing}
                                aria-invalid={Boolean(formulario.errors.nombre)}
                                aria-describedby={formulario.errors.nombre ? "error-rol-nombre" : undefined}
                            />
                            <FieldError id="error-rol-nombre">{formulario.errors.nombre}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(formulario.errors.descripcion)}>
                            <FieldLabel htmlFor="rol-descripcion">Descripción (opcional)</FieldLabel>
                            <Textarea
                                id="rol-descripcion"
                                value={formulario.data.descripcion}
                                onChange={(evento) => {
                                    formulario.setData("descripcion", evento.target.value);
                                    formulario.clearErrors("descripcion");
                                }}
                                maxLength={300}
                                disabled={formulario.processing}
                                aria-invalid={Boolean(formulario.errors.descripcion)}
                                aria-describedby={formulario.errors.descripcion ? "error-rol-descripcion" : undefined}
                            />
                            <FieldError id="error-rol-descripcion">{formulario.errors.descripcion}</FieldError>
                        </Field>
                    </FieldGroup>
                    <FormError message={errorPeticion} />
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" disabled={formulario.processing} onClick={onCerrar}>Cancelar</Button>
                        <Button type="submit" disabled={!puedeGuardar}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? "Guardando…" : "Guardar"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
