import { useState } from "react";
import type { VisitOptions } from "@inertiajs/core";

export function useErroresPeticion() {
    const [errorPeticion, setErrorPeticion] = useState<string | null>(null);
    const opcionesPeticion: Pick<
        VisitOptions,
        "onStart" | "onNetworkError" | "onHttpException"
    > = {
        onStart: () => setErrorPeticion(null),
        onNetworkError: () => {
            setErrorPeticion(
                "No hay conexión. Revisa tu internet e intenta de nuevo.",
            );
            return false;
        },
        onHttpException: (respuesta) => {
            setErrorPeticion(
                respuesta.status === 419
                    ? "La página caducó. Recárgala e intenta de nuevo."
                    : "No se pudo completar la operación. Intenta de nuevo.",
            );
            return false;
        },
    };

    return { errorPeticion, opcionesPeticion };
}
