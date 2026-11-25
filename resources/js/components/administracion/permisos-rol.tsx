import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import type { ModuloPermisos, RolDetalle } from "./tipos";

export function PermisosRol({ rol, catalogo }: { rol: RolDetalle; catalogo: ModuloPermisos[] }) {
    const formulario = useForm<{ permisos: string[]; rol?: string }>({
        permisos: [...rol.permisos].sort(),
    });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const seleccionados = new Set(formulario.data.permisos);
    const requiereSeparacion = seleccionados.has("comisiones.procesar") &&
        (seleccionados.has("comisiones.aprobar") || seleccionados.has("comisiones.rechazar"));
    const errorPermisos = formulario.errors.permisos ??
        Object.entries(formulario.errors).find(([campo]) => campo.startsWith("permisos."))?.[1];

    function cambiarPermiso(codigo: string, marcado: boolean): void {
        const permisos = new Set(formulario.data.permisos);
        if (marcado) {
            permisos.add(codigo);
        } else {
            permisos.delete(codigo);
        }
        formulario.setData("permisos", [...permisos].sort());
        formulario.clearErrors();
    }

    function guardar(): void {
        if (!formulario.isDirty || formulario.processing) {
            return;
        }
        formulario.put(route("administracion.roles.permisos", rol.codigo), {
            ...opcionesPeticion,
            preserveScroll: true,
            onSuccess: () => {
                toast.success("Permisos actualizados");
            },
        });
    }

    return (
        <form className="space-y-4" aria-label="Permisos del rol" aria-busy={formulario.processing} onSubmit={(evento) => {
            evento.preventDefault();
            guardar();
        }}>
            {requiereSeparacion && (
                <Alert>
                    <AlertDescription>Quien procese una liquidación no podrá aprobarla ni rechazarla.</AlertDescription>
                </Alert>
            )}
            <div className="grid items-start gap-4 lg:grid-cols-2">
                {catalogo.map(({ modulo, permisos }) => (
                    <fieldset key={modulo} className="min-w-0 rounded-lg border px-4 pb-2">
                        <legend className="px-1 font-semibold">{modulo}</legend>
                        <div className="divide-y">
                            {permisos.map((permiso) => (
                                <label key={permiso.codigo} className="flex items-start gap-3 py-3">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={!permiso.exclusivo && seleccionados.has(permiso.codigo)}
                                        disabled={permiso.fijo || permiso.exclusivo || formulario.processing}
                                        aria-labelledby={`titulo-${permiso.codigo}`}
                                        aria-describedby={`descripcion-${permiso.codigo}`}
                                        onCheckedChange={(marcado) => cambiarPermiso(permiso.codigo, marcado)}
                                    />
                                    <span className="min-w-0 space-y-1 text-sm">
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span id={`titulo-${permiso.codigo}`} className="font-medium">{permiso.nombre}</span>
                                            {permiso.fijo && <Badge variant="outline">Fijo</Badge>}
                                            {permiso.exclusivo && <Badge variant="outline">Solo Administrador</Badge>}
                                        </span>
                                        <span id={`descripcion-${permiso.codigo}`} className="block text-muted-foreground">
                                            {permiso.descripcion}
                                            {permiso.exclusivo && " Solo el rol Administrador puede tener este permiso."}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                ))}
            </div>
            <FormError message={errorPermisos ?? formulario.errors.rol ?? errorPeticion} />
            <div className="flex justify-end">
                <Button type="submit" disabled={!formulario.isDirty || formulario.processing}>
                    {formulario.processing && <Spinner aria-hidden="true" />}
                    {formulario.processing ? "Guardando…" : "Guardar permisos"}
                </Button>
            </div>
        </form>
    );
}
