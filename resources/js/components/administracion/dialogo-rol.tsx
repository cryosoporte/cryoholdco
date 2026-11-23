import { useForm } from "@inertiajs/react";
import { useRef } from "react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from "@/components/ui/field";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import { opcionesRol, type PersonaUsuario } from "./tipos";

const opcionesDialogoRol = Object.values(opcionesRol);

export function DialogoRol({
    persona,
    onCerrar,
}: {
    persona: PersonaUsuario;
    onCerrar: () => void;
}) {
    const selectorRol = useRef<HTMLButtonElement>(null);
    const formulario = useForm<{ rol: keyof typeof opcionesRol }>({
        rol: persona.rol ?? "sin-rol",
    });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const puedeGuardar = formulario.isDirty || persona.rol_incompleto;
    const errorRol = formulario.errors.rol;

    function guardar(): void {
        if (!puedeGuardar || formulario.processing) {
            return;
        }

        const quitaRol = formulario.data.rol === "sin-rol";
        formulario.transform(({ rol }) => ({
            rol: rol === "sin-rol" ? null : rol,
        }));
        formulario.put(route("administracion.usuarios.rol", persona.id), {
            ...opcionesPeticion,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(quitaRol ? "Rol quitado" : "Rol actualizado", {
                    description: persona.nombre,
                });
                onCerrar();
            },
        });
    }

    return (
        <Dialog
            open
            onOpenChange={(abierto) => {
                if (!abierto && !formulario.processing) {
                    onCerrar();
                }
            }}
        >
            <DialogContent initialFocus={selectorRol}>
                <form
                    className="grid gap-4"
                    aria-busy={formulario.processing}
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        guardar();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>Rol de {persona.nombre}</DialogTitle>
                        <DialogDescription>
                            {persona.cuentas === 1
                                ? "Se aplicará a su cuenta de usuario."
                                : `Se aplicará a sus ${persona.cuentas} cuentas de usuario, una por empresa.`}
                        </DialogDescription>
                    </DialogHeader>

                    {persona.rol_incompleto && (
                        <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                            El rol no coincide en todas sus cuentas. Guarda para
                            unificarlo.
                        </p>
                    )}

                    <Field data-invalid={Boolean(errorRol)}>
                        <FieldLabel id="etiqueta-rol">Rol</FieldLabel>
                        <Select
                            items={opcionesDialogoRol}
                            value={formulario.data.rol}
                            onValueChange={(rol) => {
                                if (rol !== null) {
                                    formulario.setData("rol", rol);
                                    formulario.clearErrors("rol");
                                }
                            }}
                            disabled={formulario.processing}
                        >
                            <SelectTrigger
                                ref={selectorRol}
                                aria-labelledby="etiqueta-rol"
                                aria-describedby={
                                    errorRol ? "ayuda-rol error-rol" : "ayuda-rol"
                                }
                                aria-invalid={Boolean(errorRol)}
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {opcionesDialogoRol.map(({ value, label }) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldDescription id="ayuda-rol">
                            {opcionesRol[formulario.data.rol].descripcion}
                        </FieldDescription>
                        <FieldError id="error-rol">{errorRol}</FieldError>
                    </Field>

                    <FormError message={errorPeticion} />

                    <div className="flex justify-end">
                        <Button
                            type="submit"
                            className="cursor-pointer"
                            disabled={!puedeGuardar || formulario.processing}
                        >
                            {formulario.processing && (
                                <Spinner aria-hidden="true" />
                            )}
                            {formulario.processing ? "Guardando…" : "Guardar"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
