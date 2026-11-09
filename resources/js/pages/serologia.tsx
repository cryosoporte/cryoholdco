import AppLayout from "@/layouts/app-layout";

import { useEffect, useState } from "react";
import { router } from "@inertiajs/react";
import { route } from "ziggy-js";

import { DetalleCliente } from "@/components/serologia/detalle-cliente";
import { TablaResultados } from "@/components/serologia/tabla-resultados";
import { TarjetasEstado } from "@/components/serologia/tarjetas-estado";
import { FiltrosSerologia } from "@/components/serologia/filtros-serologia";
import { PaginacionResultados } from "@/components/serologia/paginacion-resultados";

import type {
    Resultado,
    Asesor,
    ResultadosPaginados,
    EstadoSerologia,
} from "@/components/serologia/tipos";

export default function Serologia({
    resultados,
    buscar,
    estado,
    conteos,
    asesor,
    asesores,
}: {
    resultados: ResultadosPaginados;
    buscar: string;
    estado: EstadoSerologia | null;
    conteos: Record<EstadoSerologia, number>;
    asesor: string | null;
    asesores: Asesor[];
}) {
    const [texto, setTexto] = useState(buscar);
    const [estadoSeleccionado, setEstadoSeleccionado] = useState(estado);
    const [asesorSeleccionado, setAsesorSeleccionado] = useState(asesor);
    const [cargando, setCargando] = useState(false);
    const [clienteSeleccionado, setClienteSeleccionado] =
        useState<Resultado | null>(null);
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

    return (
        <AppLayout title="Operaciones - Serología">
            <h1 className="text-2xl font-semibold">Serología de clientes</h1>

            <TarjetasEstado
                conteos={conteos}
                estadoSeleccionado={estadoSeleccionado}
                onSeleccionarEstado={(opcion) =>
                    setEstadoSeleccionado((actual) =>
                        actual === opcion ? null : opcion,
                    )
                }
            />

            <FiltrosSerologia
                texto={texto}
                asesorSeleccionado={asesorSeleccionado}
                estadoSeleccionado={estadoSeleccionado}
                asesores={asesores}
                onCambiarTexto={setTexto}
                onCambiarAsesor={setAsesorSeleccionado}
                onLimpiarEstado={() => setEstadoSeleccionado(null)}
            />

            {errorFiltros && (
                <p
                    role="alert"
                    className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                >
                    {errorFiltros}
                </p>
            )}

            <p role="status" className="min-h-5 text-sm text-muted-foreground">
                {actualizando ? "Actualizando resultados..." : ""}
            </p>

            <TablaResultados
                resultados={resultados.data}
                actualizando={actualizando}
                onSeleccionarCliente={setClienteSeleccionado}
            />

            <PaginacionResultados
                paginacion={resultados}
                actualizando={actualizando}
                onCambiarPagina={(url) => {
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
                }}
            />

            <DetalleCliente
                cliente={clienteSeleccionado}
                onCerrar={() => setClienteSeleccionado(null)}
            />
        </AppLayout>
    );
}
