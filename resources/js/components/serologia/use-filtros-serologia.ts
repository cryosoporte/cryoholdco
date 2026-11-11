import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { route } from "ziggy-js";
import type { EstadoSerologia } from "@/components/serologia/tipos";

type FiltrosDelServidor = {
    buscar: string;
    estado: EstadoSerologia | null;
    asesor: string | null;
};

/**
 * Mantiene los filtros del listado sincronizados con el servidor: espera a que
 * se deje de escribir antes de buscar, cancela la petición anterior y, si la
 * consulta falla, restaura los filtros que devolvió el servidor.
 */
export function useFiltrosSerologia({ buscar, estado, asesor }: FiltrosDelServidor) {
    const [texto, setTexto] = useState(buscar);
    const [estadoSeleccionado, setEstadoSeleccionado] = useState(estado);
    const [asesorSeleccionado, setAsesorSeleccionado] = useState(asesor);
    const [cargando, setCargando] = useState(false);
    const [errorFiltros, setErrorFiltros] = useState<string | null>(null);

    const filtrosPendientes =
        texto.trim() !== buscar ||
        estadoSeleccionado !== estado ||
        asesorSeleccionado !== asesor;
    const actualizando = filtrosPendientes || cargando;

    useEffect(() => {
        if (
            texto.trim() === buscar &&
            estadoSeleccionado === estado &&
            asesorSeleccionado === asesor
        )
            return;

        function recuperarFiltros(mensaje: string): void {
            setTexto(buscar);
            setEstadoSeleccionado(estado);
            setAsesorSeleccionado(asesor);
            setErrorFiltros(mensaje);
        }

        let cancelarPeticion: (() => void) | undefined;

        const temporizador = setTimeout(
            () => {
                router.get(
                    route("serologia"),
                    {
                        buscar: texto.trim(),
                        ...(estadoSeleccionado
                            ? { estado: estadoSeleccionado }
                            : {}),
                        ...(asesorSeleccionado
                            ? { asesor: asesorSeleccionado }
                            : {}),
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
            estadoSeleccionado !== estado || asesorSeleccionado !== asesor
                ? 0
                : 350,
        );

        return () => {
            clearTimeout(temporizador);
            cancelarPeticion?.();
        };
    }, [texto, buscar, estadoSeleccionado, estado, asesorSeleccionado, asesor]);

    function alternarEstado(opcion: EstadoSerologia): void {
        setEstadoSeleccionado((actual) => (actual === opcion ? null : opcion));
    }

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

    return {
        texto,
        setTexto,
        estadoSeleccionado,
        alternarEstado,
        limpiarEstado: () => setEstadoSeleccionado(null),
        asesorSeleccionado,
        setAsesorSeleccionado,
        errorFiltros,
        actualizando,
        cambiarPagina,
    };
}
