import { useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import { BuildingIcon, CheckIcon, MapPinIcon } from "lucide-react";
import { route } from "ziggy-js";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import { cn } from "@/lib/utils";
import type { OpcionLaboratorio } from "./tipos";

/**
 * La elección de sede que el laboratorio hace al entrar al mostrador. Cada sede es
 * un botón grande, pensado para el mostrador, y no se entra hasta confirmar una.
 */
export function EleccionSede({
    sedes,
    sede,
    nombreLaboratorio,
}: {
    sedes: OpcionLaboratorio[];
    sede: string | null;
    nombreLaboratorio: string | null;
}) {
    const { errors } = usePage().props;
    const [elegida, setElegida] = useState<string | null>(sede);
    const formulario = useForm({ sede: sede ?? "" });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <h1>Elige tu sede</h1>
                </CardTitle>
                <CardDescription>
                    {nombreLaboratorio
                        ? `${nombreLaboratorio} atiende en varias sedes.`
                        : "Tu laboratorio atiende en varias sedes."}{" "}
                    Indica desde cuál vas a registrar las muestras de hoy.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        if (elegida === null || formulario.processing) {
                            return;
                        }
                        formulario.transform(() => ({ sede: elegida }));
                        formulario.post(route("laboratorio.sede"), {
                            ...opcionesPeticion,
                        });
                    }}
                    aria-busy={formulario.processing}
                    className="space-y-5"
                >
                    <fieldset
                        className="space-y-2"
                        disabled={formulario.processing}
                    >
                        <legend className="sr-only">Sedes activas</legend>
                        {sedes.map((opcion) => (
                            <label
                                key={opcion.codigo}
                                className={cn(
                                    "flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors",
                                    "hover:bg-accent/50 has-focus-visible:ring-3 has-focus-visible:ring-ring/50",
                                    elegida === opcion.codigo
                                        ? "border-primary bg-accent/40"
                                        : "border-input",
                                )}
                            >
                                <input
                                    type="radio"
                                    name="sede"
                                    value={opcion.codigo}
                                    checked={elegida === opcion.codigo}
                                    onChange={() => setElegida(opcion.codigo)}
                                    className="sr-only"
                                />
                                <BuildingIcon
                                    className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block font-medium">
                                        {opcion.nombre}
                                    </span>
                                    {(opcion.direccion ?? opcion.ciudad) && (
                                        <span className="mt-0.5 flex items-start gap-1 text-sm text-muted-foreground">
                                            <MapPinIcon
                                                className="mt-0.5 size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {[opcion.direccion, opcion.ciudad]
                                                .filter(Boolean)
                                                .join(", ")}
                                        </span>
                                    )}
                                </span>
                                {elegida === opcion.codigo && (
                                    <CheckIcon
                                        className="mt-0.5 size-5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                )}
                            </label>
                        ))}
                    </fieldset>
                    <FormError message={errors.sede ?? errorPeticion} />
                    <Button
                        type="submit"
                        className="w-full cursor-pointer"
                        disabled={elegida === null || formulario.processing}
                    >
                        {formulario.processing && <Spinner aria-hidden="true" />}
                        {formulario.processing ? "Entrando…" : "Entrar al mostrador"}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
