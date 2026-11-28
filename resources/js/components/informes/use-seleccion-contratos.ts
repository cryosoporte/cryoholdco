import { useState } from "react";
import { toast } from "@/components/ui/sonner";

import {
    aSeleccionado,
    MAXIMO_SELECCION,
    puedeSeleccionarse,
} from "./configuracion";
import type { ContratoInforme, ContratoSeleccionado } from "./tipos";

/**
 * Los contratos marcados para el envío masivo. La selección sobrevive al cambio de
 * página y de filtros, y se identifica por el contrato base.
 */
export function useSeleccionContratos() {
    const [seleccionados, setSeleccionados] = useState<
        Record<string, ContratoSeleccionado>
    >({});

    const lista = Object.values(seleccionados);

    function estaMarcado(contrato: ContratoInforme): boolean {
        return contrato.numero_base in seleccionados;
    }

    function marcar(contratos: ContratoInforme[]): void {
        setSeleccionados((actuales) => {
            const siguientes = { ...actuales };

            for (const contrato of contratos) {
                const seleccionado = aSeleccionado(contrato);

                if (!puedeSeleccionarse(contrato)) {
                    continue;
                }

                if (
                    !(contrato.numero_base in siguientes) &&
                    Object.keys(siguientes).length >= MAXIMO_SELECCION
                ) {
                    toast.error(
                        `Solo puedes marcar hasta ${MAXIMO_SELECCION} contratos a la vez.`,
                    );
                    break;
                }

                siguientes[contrato.numero_base] = seleccionado;
            }

            return siguientes;
        });
    }

    function desmarcar(contratos: ContratoInforme[]): void {
        setSeleccionados((actuales) => {
            const siguientes = { ...actuales };

            for (const contrato of contratos) {
                delete siguientes[contrato.numero_base];
            }

            return siguientes;
        });
    }

    function alternar(contrato: ContratoInforme, marcado: boolean): void {
        if (marcado) {
            marcar([contrato]);
        } else {
            desmarcar([contrato]);
        }
    }

    return {
        lista,
        total: lista.length,
        estaMarcado,
        alternar,
        marcar,
        desmarcar,
        limpiar: () => setSeleccionados({}),
    };
}
