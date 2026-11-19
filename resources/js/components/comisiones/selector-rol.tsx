import { useState } from "react";
import { router } from "@inertiajs/react";
import { route } from "ziggy-js";
import { FormError } from "@/components/form-error";
import { Badge } from "@/components/ui/badge";
import { Spinner } from "@/components/ui/spinner";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import type { RolDemo } from "./tipos";
import { useAccionComisiones } from "./use-accion-comisiones";

export function SelectorRol({ rol }: { rol: RolDemo }) {
    const [procesando, setProcesando] = useState(false);
    const [errorRol, setErrorRol] = useState<string>();
    const { errorPeticion, opciones } = useAccionComisiones();

    function cambiarRol([seleccion]: unknown[]): void {
        if (procesando || seleccion === rol || (seleccion !== "analista" && seleccion !== "gerenta")) {
            return;
        }

        setErrorRol(undefined);
        setProcesando(true);
        router.post(route("reportes.comisiones.demo.rol"), { rol: seleccion }, {
            ...opciones,
            onError: (errores) => setErrorRol(errores.rol),
            onFinish: () => setProcesando(false),
        });
    }

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2" aria-busy={procesando}>
                <Badge variant="outline">Demo</Badge>
                <span className="text-sm text-muted-foreground">Ver como</span>
                <ToggleGroup
                    value={[rol]}
                    onValueChange={cambiarRol}
                    variant="outline"
                    spacing={0}
                    disabled={procesando}
                    aria-label="Ver como"
                >
                    <ToggleGroupItem value="analista">Analista</ToggleGroupItem>
                    <ToggleGroupItem value="gerenta">Gerenta</ToggleGroupItem>
                </ToggleGroup>
                {procesando && <Spinner aria-label="Cambiando rol" />}
            </div>
            <FormError message={errorRol ?? errorPeticion} />
        </div>
    );
}
