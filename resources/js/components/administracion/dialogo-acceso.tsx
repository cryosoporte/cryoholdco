import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
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
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { toast } from "@/components/ui/sonner";
import { Spinner } from "@/components/ui/spinner";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import {
    opcionesEstadoAcceso,
    type EstadoAcceso,
    type LaboratorioAcceso,
} from "./tipos";

type DialogoAccesoProps = {
    laboratorio: LaboratorioAcceso | null;
    onCerrar: () => void;
};

export function DialogoAcceso({ laboratorio, onCerrar }: DialogoAccesoProps) {
    return (
        <Dialog
            open={laboratorio !== null}
            onOpenChange={(abierto) => {
                if (!abierto) {
                    onCerrar();
                }
            }}
        >
            <DialogContent>
                {laboratorio && (
                    <FormularioAcceso
                        key={laboratorio.identificacion}
                        laboratorio={laboratorio}
                        onGuardado={onCerrar}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function FormularioAcceso({
    laboratorio,
    onGuardado,
}: {
    laboratorio: LaboratorioAcceso;
    onGuardado: () => void;
}) {
    const acceso = laboratorio.acceso;
    const nombre = laboratorio.nombre ?? laboratorio.identificacion;
    const formulario = useForm({
        identificacion: laboratorio.identificacion,
        password: "",
        password_confirmation: "",
        estado: (acceso?.estado ?? "A") as EstadoAcceso,
    });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    function guardar(): void {
        if (formulario.processing) {
            return;
        }

        const opciones = {
            ...opcionesPeticion,
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    acceso === null ? "Acceso creado" : "Acceso actualizado",
                    { description: nombre },
                );
                onGuardado();
            },
        };

        if (acceso === null) {
            formulario.post(route("administracion.laboratorios.crear"), opciones);
        } else {
            formulario.put(
                route("administracion.laboratorios.actualizar", acceso.id),
                opciones,
            );
        }
    }

    return (
        <form
            onSubmit={(evento) => {
                evento.preventDefault();
                guardar();
            }}
            className="grid gap-4"
            aria-busy={formulario.processing}
        >
            <DialogHeader>
                <DialogTitle>
                    {acceso === null ? "Crear acceso" : "Editar acceso"}
                </DialogTitle>
                <DialogDescription>
                    {nombre} ingresará al mostrador con el RUC{" "}
                    <span className="font-mono">{laboratorio.identificacion}</span>{" "}
                    y esta contraseña.
                </DialogDescription>
            </DialogHeader>

            <FormError message={formulario.errors.identificacion} />

            <FieldGroup>
                <Field data-invalid={!!formulario.errors.password}>
                    <FieldLabel htmlFor="password">
                        {acceso === null ? "Contraseña" : "Nueva contraseña"}
                    </FieldLabel>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        value={formulario.data.password}
                        onChange={(evento) =>
                            formulario.setData("password", evento.target.value)
                        }
                        required={acceso === null}
                        minLength={8}
                        maxLength={72}
                        disabled={formulario.processing}
                        aria-invalid={!!formulario.errors.password}
                        aria-describedby="ayuda-password error-password"
                        autoFocus
                    />
                    <FieldDescription id="ayuda-password">
                        {acceso === null
                            ? "Mínimo 8 caracteres."
                            : "Déjala en blanco para conservar la contraseña actual."}
                    </FieldDescription>
                    <FieldError id="error-password">
                        {formulario.errors.password}
                    </FieldError>
                </Field>

                <Field>
                    <FieldLabel htmlFor="password_confirmation">
                        Repite la contraseña
                    </FieldLabel>
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={formulario.data.password_confirmation}
                        onChange={(evento) =>
                            formulario.setData(
                                "password_confirmation",
                                evento.target.value,
                            )
                        }
                        required={
                            acceso === null || formulario.data.password !== ""
                        }
                        maxLength={72}
                        disabled={formulario.processing}
                    />
                </Field>

                <Field data-invalid={!!formulario.errors.estado}>
                    <FieldLabel id="etiqueta-estado">Estado del acceso</FieldLabel>
                    <Select
                        items={opcionesEstadoAcceso}
                        value={formulario.data.estado}
                        onValueChange={(valor) =>
                            formulario.setData("estado", valor as EstadoAcceso)
                        }
                        disabled={formulario.processing}
                    >
                        <SelectTrigger
                            aria-labelledby="etiqueta-estado"
                            className="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {opcionesEstadoAcceso.map((opcion) => (
                                <SelectItem
                                    key={opcion.value}
                                    value={opcion.value}
                                >
                                    {opcion.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <FieldError>{formulario.errors.estado}</FieldError>
                </Field>
            </FieldGroup>

            <FormError message={errorPeticion} />

            <div className="flex justify-end gap-2">
                <Button
                    type="submit"
                    className="cursor-pointer"
                    disabled={formulario.processing}
                >
                    {formulario.processing && <Spinner aria-hidden="true" />}
                    {formulario.processing ? "Guardando…" : "Guardar"}
                </Button>
            </div>
        </form>
    );
}
