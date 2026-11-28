import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { route } from "ziggy-js";

import type { FiltrosTarjetas } from "./tipos";

type FiltrosDelServidor = {
    buscar: string;
} & FiltrosTarjetas;

const CLAVES_TARJETAS: (keyof FiltrosTarjetas)[] = [
    "envio",
    "antiguedad",
    "sangre",
    "tejido",
    "liberacion",
];

/**
 * Mantiene los filtros del listado sincronizados con el servidor: espera a que
 * se deje de escribir antes de buscar, cancela la petición anterior y, si la
 * consulta falla, restaura los filtros que devolvió el servidor. Los filtros de
 * las tarjetas (envío, antigüedad, sangre y tejido) se aplican al instante y se
 * combinan entre sí.
 */
export function useFiltrosInformes({
    buscar,
    envio,
    antiguedad,
    sangre,
    tejido,
    liberacion,
}: FiltrosDelServidor) {
    const filtrosServidor: FiltrosTarjetas = {
        envio,
        antiguedad,
        sangre,
        tejido,
        liberacion,
    };
    const [texto, setTexto] = useState(buscar);
    const [filtros, setFiltros] = useState<FiltrosTarjetas>(filtrosServidor);
    const [cargando, setCargando] = useState(false);
    const [errorFiltros, setErrorFiltros] = useState<string | null>(null);

    const tarjetasPendientes = CLAVES_TARJETAS.some(
        (clave) => filtros[clave] !== filtrosServidor[clave],
    );
    const filtrosPendientes = texto.trim() !== buscar || tarjetasPendientes;
    const actualizando = filtrosPendientes || cargando;

    useEffect(() => {
        if (texto.trim() === buscar && !tarjetasPendientes) return;

        function recuperarFiltros(mensaje: string): void {
            setTexto(buscar);
            setFiltros({ envio, antiguedad, sangre, tejido, liberacion });
            setErrorFiltros(mensaje);
        }

        let cancelarPeticion: (() => void) | undefined;

        const temporizador = setTimeout(
            () => {
                router.get(
                    route("informes"),
                    {
                        buscar: texto.trim(),
                        // Sin «envio» el servidor muestra los pendientes: «todos» pide enviados y no enviados.
                        envio: filtros.envio ?? "todos",
                        ...Object.fromEntries(
                            CLAVES_TARJETAS.filter(
                                (clave) =>
                                    clave !== "envio" && filtros[clave] !== null,
                            ).map((clave) => [clave, filtros[clave]]),
                        ),
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,

                        onStart: () => {
                            setErrorFiltros(null);
                            setCargando(true);
                        },

                        onError: () => {
                            recuperarFiltros(
                                "No se pudieron aplicar los filtros. Se restauraron los anteriores.",
                            );
                        },

                        onNetworkError: () => {
                            recuperarFiltros(
                                "No se pudo conectar con el servidor. Se restauraron los filtros anteriores.",
                            );

                            return false;
                        },

                        onHttpException: () => {
                            recuperarFiltros(
                                "El servidor no pudo completar la consulta. Se restauraron los filtros anteriores.",
                            );

                            return false;
                        },

                        onFinish: () => {
                            setCargando(false);
                        },

                        onCancelToken: (token) => {
                            cancelarPeticion = () => token.cancel();
                        },
                    },
                );
            },
            tarjetasPendientes ? 0 : 350,
        );

        return () => {
            clearTimeout(temporizador);
            cancelarPeticion?.();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [texto, buscar, filtros, envio, antiguedad, sangre, tejido, liberacion]);

    function cambiarPagina(url: string): void {
        router.get(
            url,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setCargando(true),
                onFinish: () => setCargando(false),
            },
        );
    }

    function cambiarFiltro(
        clave: keyof FiltrosTarjetas,
        valor: string | null,
    ): void {
        setFiltros((actuales) => ({ ...actuales, [clave]: valor }));
    }

    /** Aplica el filtro de una tarjeta; si ya estaba aplicado, lo quita. */
    function alternarFiltro(clave: keyof FiltrosTarjetas, valor: string): void {
        cambiarFiltro(clave, filtros[clave] === valor ? null : valor);
    }

    function limpiarFiltrosTarjetas(): void {
        setFiltros({
            envio: null,
            antiguedad: null,
            sangre: null,
            tejido: null,
            liberacion: null,
        });
    }

    return {
        texto,
        setTexto,
        filtros,
        cambiarFiltro,
        alternarFiltro,
        limpiarFiltrosTarjetas,
        errorFiltros,
        actualizando,
        cambiarPagina,
    };
}
