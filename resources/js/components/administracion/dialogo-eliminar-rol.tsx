import { useForm } from "@inertiajs/react";
import { useRef } from "react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import type { RolResumen } from "./tipos";

export function DialogoEliminarRol({ rol, onCerrar }: { rol: RolResumen; onCerrar: () => void }) {
    const botonCancelar = useRef<HTMLButtonElement>(null);
    const formulario = useForm<{ rol?: string }>({});
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    function eliminar(): void {
        if (formulario.processing) {
            return;
        }

        formulario.delete(route("administracion.roles.eliminar", rol.codigo), {
            ...opcionesPeticion,
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Rol eliminado", { description: rol.nombre });
                onCerrar();
            },
        });
    }

    return (
        <Dialog open onOpenChange={(abierto) => {
            if (!abierto && !formulario.processing) onCerrar();
        }}>
            <DialogContent initialFocus={botonCancelar}>
                <form className="grid gap-4" aria-busy={formulario.processing} onSubmit={(evento) => {
                    evento.preventDefault();
                    eliminar();
                }}>
                    <DialogHeader>
                        <DialogTitle>Eliminar rol</DialogTitle>
                        <DialogDescription>Se eliminará el rol «{rol.nombre}» y sus permisos.</DialogDescription>
                    </DialogHeader>
                    <FormError message={formulario.errors.rol ?? errorPeticion} />
                    <div className="flex justify-end gap-2">
                        <Button ref={botonCancelar} type="button" variant="outline" disabled={formulario.processing} onClick={onCerrar}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="destructive" disabled={formulario.processing}>
                            {formulario.processing && <Spinner aria-hidden="true" />}
                            {formulario.processing ? "Eliminando…" : "Eliminar rol"}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
