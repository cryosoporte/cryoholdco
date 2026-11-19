import type { VisitOptions } from "@inertiajs/core";
import { toast } from "@/components/ui/sonner";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";

export function useAccionComisiones() {
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    const opciones: Pick<
        VisitOptions,
        "preserveScroll" | "onFlash" | "onStart" | "onNetworkError" | "onHttpException"
    > = {
        ...opcionesPeticion,
        preserveScroll: true,
        onFlash: ({ comisiones }) => {
            if (!comisiones) {
                return;
            }

            const avisar = comisiones.tipo === "exito" ? toast.success : toast.warning;
            avisar(comisiones.titulo, { description: comisiones.descripcion });
        },
    };

    return { errorPeticion, opciones };
}
