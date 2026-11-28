import AppLayout from "@/layouts/app-layout";

import { useState } from "react";
import { router } from "@inertiajs/react";

import { Button } from "@/components/ui/button";
import { FormError } from "@/components/form-error";
import { DocumentosContrato } from "@/components/informes/documentos-contrato";
import { EnvioMasivo } from "@/components/informes/envio-masivo";
import { FiltrosInformes } from "@/components/informes/filtros-informes";
import { TablaContratos } from "@/components/informes/tabla-contratos";
import { TarjetasResumen } from "@/components/informes/tarjetas-resumen";
import { useFiltrosInformes } from "@/components/informes/use-filtros-informes";
import { useSeleccionContratos } from "@/components/informes/use-seleccion-contratos";
import { PaginacionResultados } from "@/components/serologia/paginacion-resultados";

import type {
    ContratoInforme,
    ContratosPaginados,
    ResumenInformes,
} from "@/components/informes/tipos";

export default function Informes({
    resultados,
    resumen,
    buscar,
    envio,
    antiguedad,
    sangre,
    tejido,
    liberacion,
    unidad_disponible,
    maximo_envio_masivo,
}: {
    resultados: ContratosPaginados;
    resumen: ResumenInformes;
    buscar: string;
    envio: string | null;
    antiguedad: string | null;
    sangre: string | null;
    tejido: string | null;
    liberacion: string | null;
    unidad_disponible: boolean;
    maximo_envio_masivo: number;
}) {
    const filtros = useFiltrosInformes({
        buscar,
        envio,
        antiguedad,
        sangre,
        tejido,
        liberacion,
    });
    const seleccion = useSeleccionContratos();
    const [contratoConDocumentos, setContratoConDocumentos] =
        useState<ContratoInforme | null>(null);
    const [envioMasivoAbierto, setEnvioMasivoAbierto] = useState(false);

    return (
        <AppLayout title="Operaciones - Informes">
            <h1 className="text-2xl font-semibold">Informes de contratos</h1>

            <FiltrosInformes
                texto={filtros.texto}
                envioSeleccionado={filtros.filtros.envio}
                onCambiarEnvio={(valor) => filtros.cambiarFiltro("envio", valor)}
                onCambiarTexto={filtros.setTexto}
            />

            <TarjetasResumen
                resumen={resumen}
                filtros={filtros.filtros}
                deshabilitado={filtros.actualizando}
                onAlternar={filtros.alternarFiltro}
                onLimpiar={filtros.limpiarFiltrosTarjetas}
            />

            <FormError message={filtros.errorFiltros} />
            <FormError
                message={
                    unidad_disponible
                        ? null
                        : "No se pudo acceder a la unidad de documentos. Los contratos se listan, pero no se sabe cuáles tienen carpeta."
                }
            />

            <div className="flex min-h-9 flex-wrap items-center justify-between gap-3">
                <p role="status" className="text-sm text-muted-foreground">
                    {filtros.actualizando
                        ? "Actualizando resultados..."
                        : seleccion.total > 0
                          ? `${seleccion.total} ${seleccion.total === 1 ? "contrato marcado" : "contratos marcados"}`
                          : ""}
                </p>

                {seleccion.total > 0 && (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="cursor-pointer"
                            onClick={seleccion.limpiar}
                        >
                            Quitar selección
                        </Button>
                        <Button
                            type="button"
                            className="cursor-pointer"
                            onClick={() => setEnvioMasivoAbierto(true)}
                        >
                            Enviar documentos a {seleccion.total}
                        </Button>
                    </div>
                )}
            </div>

            <TablaContratos
                contratos={resultados.data}
                actualizando={filtros.actualizando}
                estaMarcado={seleccion.estaMarcado}
                onAlternar={seleccion.alternar}
                onAlternarPagina={(contratos, marcado) =>
                    marcado
                        ? seleccion.marcar(contratos)
                        : seleccion.desmarcar(contratos)
                }
                onVerDocumentos={setContratoConDocumentos}
            />

            <PaginacionResultados
                paginacion={resultados}
                actualizando={filtros.actualizando}
                onCambiarPagina={filtros.cambiarPagina}
            />

            <DocumentosContrato
                contrato={contratoConDocumentos}
                onCerrar={() => setContratoConDocumentos(null)}
            />

            <EnvioMasivo
                abierto={envioMasivoAbierto}
                contratos={seleccion.lista}
                tamanoLote={maximo_envio_masivo}
                onCerrar={(huboEnvios) => {
                    setEnvioMasivoAbierto(false);

                    if (huboEnvios) {
                        seleccion.limpiar();
                        router.reload({ only: ["resultados", "resumen"] });
                    }
                }}
            />
        </AppLayout>
    );
}
