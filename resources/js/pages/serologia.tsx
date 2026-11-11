import AppLayout from "@/layouts/app-layout";

import { useState } from "react";

import { DetalleCliente } from "@/components/serologia/detalle-cliente";
import { TablaResultados } from "@/components/serologia/tabla-resultados";
import { TarjetasEstado } from "@/components/serologia/tarjetas-estado";
import { FiltrosSerologia } from "@/components/serologia/filtros-serologia";
import { PaginacionResultados } from "@/components/serologia/paginacion-resultados";
import { useFiltrosSerologia } from "@/components/serologia/use-filtros-serologia";
import { FormError } from "@/components/form-error";

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
    const filtros = useFiltrosSerologia({ buscar, estado, asesor });
    const [clienteSeleccionado, setClienteSeleccionado] =
        useState<Resultado | null>(null);

    return (
        <AppLayout title="Operaciones - Serología">
            <h1 className="text-2xl font-semibold">Serología de clientes</h1>

            <TarjetasEstado
                conteos={conteos}
                estadoSeleccionado={filtros.estadoSeleccionado}
                onSeleccionarEstado={filtros.alternarEstado}
            />

            <FiltrosSerologia
                texto={filtros.texto}
                asesorSeleccionado={filtros.asesorSeleccionado}
                estadoSeleccionado={filtros.estadoSeleccionado}
                asesores={asesores}
                onCambiarTexto={filtros.setTexto}
                onCambiarAsesor={filtros.setAsesorSeleccionado}
                onLimpiarEstado={filtros.limpiarEstado}
            />

            <FormError message={filtros.errorFiltros} />

            <p role="status" className="min-h-5 text-sm text-muted-foreground">
                {filtros.actualizando ? "Actualizando resultados..." : ""}
            </p>

            <TablaResultados
                resultados={resultados.data}
                actualizando={filtros.actualizando}
                onSeleccionarCliente={setClienteSeleccionado}
            />

            <PaginacionResultados
                paginacion={resultados}
                actualizando={filtros.actualizando}
                onCambiarPagina={filtros.cambiarPagina}
            />

            <DetalleCliente
                cliente={clienteSeleccionado}
                onCerrar={() => setClienteSeleccionado(null)}
            />
        </AppLayout>
    );
}
