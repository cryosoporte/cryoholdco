import { useRef, useState } from "react";
import { useHttp } from "@inertiajs/react";
import { route } from "ziggy-js";

import { mensajeDeError } from "./errores";
import type { ContratoSeleccionado, ResultadoEnvioMasivo } from "./tipos";

type Lote = { contratos: { numero_base: string; identificacion: string }[] };

/**
 * Envía los documentos a los contratos marcados en lotes, para que un envío grande no agote
 * el tiempo de una sola petición. Si un lote falla, sus contratos quedan como omitidos con el
 * motivo y se sigue con el siguiente, salvo que la sesión haya caducado.
 */
export function useEnvioMasivo(contratos: ContratoSeleccionado[], tamanoLote: number) {
    const http = useHttp<Lote, ResultadoEnvioMasivo>({ contratos: [] });
    const enCurso = useRef(false);
    const [procesados, setProcesados] = useState(0);
    const [resultado, setResultado] = useState<ResultadoEnvioMasivo | null>(null);
    const [enviando, setEnviando] = useState(false);

    async function enviar(): Promise<void> {
        if (enCurso.current || contratos.length === 0) {
            return;
        }

        enCurso.current = true;
        setEnviando(true);
        setProcesados(0);

        const acumulado: ResultadoEnvioMasivo = { enviados: [], omitidos: [] };
        setResultado(acumulado);

        for (let inicio = 0; inicio < contratos.length; inicio += tamanoLote) {
            const lote = contratos.slice(inicio, inicio + tamanoLote);
            http.transform(() => ({
                contratos: lote.map(({ numero_base, identificacion }) => ({
                    numero_base,
                    identificacion,
                })),
            }));

            try {
                await http.post(route("informes.documentos.enviar-masivo"), {
                    onSuccess: (respuesta) => {
                        acumulado.enviados.push(...respuesta.enviados);
                        acumulado.omitidos.push(...respuesta.omitidos);
                    },
                    onError: (errores) => {
                        const motivo =
                            Object.values(errores)[0] ?? "El servidor rechazó la petición.";
                        acumulado.omitidos.push(
                            ...lote.map((contrato) => ({
                                numero_base: contrato.numero_base,
                                motivo,
                            })),
                        );
                    },
                });
            } catch (fallo) {
                const motivo = mensajeDeError(
                    fallo,
                    "No se pudo confirmar el envío. Revisa la conexión.",
                );
                acumulado.omitidos.push(
                    ...lote.map((contrato) => ({
                        numero_base: contrato.numero_base,
                        motivo,
                    })),
                );
            }

            setProcesados(Math.min(inicio + lote.length, contratos.length));
            setResultado({
                enviados: [...acumulado.enviados],
                omitidos: [...acumulado.omitidos],
            });
        }

        enCurso.current = false;
        setEnviando(false);
    }

    function reiniciar(): void {
        setProcesados(0);
        setResultado(null);
    }

    return { enviar, enviando, procesados, resultado, reiniciar };
}
