import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { FieldError } from "@/components/ui/field";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import type { PersonaUsuario, RolDisponible } from "./tipos";

export function DialogoRoles({
    persona,
    rolesDisponibles,
    onCerrar,
}: {
    persona: PersonaUsuario;
    rolesDisponibles: RolDisponible[];
    onCerrar: () => void;
}) {
    const formulario = useForm({ roles: [...persona.roles].sort() });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const seleccionados = new Set(formulario.data.roles);
    const puedeGuardar = (formulario.isDirty || persona.roles_incompletos) && !formulario.processing;
    const errorRoles = formulario.errors.roles ??
        Object.entries(formulario.errors).find(([campo]) => campo.startsWith("roles."))?.[1];

    function cambiarRol(codigo: string, marcado: boolean): void {
        const roles = new Set(formulario.data.roles);
        if (marcado) {
            roles.add(codigo);
        } else {
            roles.delete(codigo);
        }
        formulario.setData("roles", [...roles].sort());
        formulario.clearErrors();
    }

    function guardar(): void {
        if (!puedeGuardar) {
            return;
        }

        formulario.put(route("administracion.usuarios.roles", persona.id), {
            ...opcionesPeticion,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(formulario.data.roles.length === 0 ? "Roles quitados" : "Roles actualizados", {
                    description: persona.nombre,
                });
                onCerrar();
            },
        });
    }

    return (
        <Dialog open onOpenChange={(abierto) => {
            if (!abierto && !formulario.processing) onCerrar();
        }}>
            <DialogContent>
                <form className="grid gap-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    guardar();
                }}>
                    <DialogHeader>
                        <DialogTitle>Roles de {persona.nombre}</DialogTitle>
                        <DialogDescription>
                            {persona.cuentas === 1
                                ? "La selección se aplicará a su cuenta de usuario."
                                : `La selección se aplicará a sus ${persona.cuentas} cuentas de usuario.`}
                        </DialogDescription>
                    </DialogHeader>
                    {persona.roles_incompletos && (
                        <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                            Sus cuentas tienen roles distintos. Se marcaron todos los roles encontrados.
                            Revisa la selección antes de aplicarla a todas sus cuentas.
                        </p>
                    )}
                    <fieldset aria-describedby={errorRoles ? "error-roles" : undefined}>
                        <legend className="font-medium">Roles disponibles</legend>
                        <div className="divide-y">
                            {rolesDisponibles.map(({ codigo, nombre, descripcion }) => (
                                <label key={codigo} className="flex items-start gap-3 py-2.5">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={seleccionados.has(codigo)}
                                        disabled={formulario.processing}
                                        aria-labelledby={`nombre-rol-${codigo}`}
                                        aria-describedby={descripcion ? `descripcion-rol-${codigo}` : undefined}
                                        onCheckedChange={(marcado) => cambiarRol(codigo, marcado)}
                                    />
                                    <span className="min-w-0 text-sm">
                                        <span id={`nombre-rol-${codigo}`} className="block font-medium">{nombre}</span>
                                        {descripcion && (
                                            <span id={`descripcion-rol-${codigo}`} className="block wrap-break-word text-muted-foreground">
                                                {descripcion}
                                            </span>
                                        )}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <FieldError id="error-roles">{errorRoles}</FieldError>
                    </fieldset>
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
