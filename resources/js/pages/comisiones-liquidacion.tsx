import { useEffect } from "react";
import { Link, usePoll } from "@inertiajs/react";
import { route } from "ziggy-js";
import { AccionesLiquidacion } from "@/components/comisiones/acciones-liquidacion";
import { BaseLiquidacion } from "@/components/comisiones/base-liquidacion";
import { EnviosPersonal } from "@/components/comisiones/envios-personal";
import { HistorialLiquidacion } from "@/components/comisiones/historial-liquidacion";
import { IndicadoresLiquidacion } from "@/components/comisiones/indicadores-liquidacion";
import { InsigniaEstado } from "@/components/comisiones/insignia-estado";
import { PasosLiquidacion } from "@/components/comisiones/pasos-liquidacion";
import { PestanasCargos } from "@/components/comisiones/pestanas-cargos";
import type { Liquidacion, PermisosLiquidacion } from "@/components/comisiones/tipos";
import { AvisoActualizacionFuente } from "@/components/reportes/aviso-actualizacion-fuente";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import AppLayout from "@/layouts/app-layout";

/** Cada cuánto se actualiza la pantalla mientras el worker envía los correos. */
const INTERVALO_ENVIO_MS = 4000;

export default function ComisionesLiquidacion({ liquidacion, periodo, meses_pendientes, envio_en_curso, permisos, aviso_separacion }: {
    liquidacion: Liquidacion | null;
    periodo: string;
    meses_pendientes: string[];
    envio_en_curso: boolean;
    permisos: PermisosLiquidacion;
    aviso_separacion: string | null;
}) {
    const titulo = liquidacion?.periodo ?? periodo;
    // Los formularios guardan la versión al montarse: al cambiar de versión se vuelven a montar.
    const version = liquidacion?.liquidacion_id ?? "borrador";
    const { start, stop } = usePoll(INTERVALO_ENVIO_MS, { only: ["liquidacion", "envio_en_curso", "permisos", "aviso_separacion"] }, { autoStart: false });

    useEffect(() => {
        if (!envio_en_curso) {
            return;
        }

        start();

        return stop;
    }, [envio_en_curso, start, stop]);

    return (
        <AppLayout title={`Comisiones - ${titulo}`}>
            <div className="min-w-0 space-y-6">
                <Breadcrumb aria-label="Ruta de navegación">
                    <BreadcrumbList>
                        <BreadcrumbItem><BreadcrumbLink render={<Link href={route("reportes")} />}>Reportes</BreadcrumbLink></BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem><BreadcrumbLink render={<Link href={route("reportes.comisiones")} />}>Comisiones</BreadcrumbLink></BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem><BreadcrumbPage>{titulo}</BreadcrumbPage></BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
                <h1 className="text-2xl font-semibold tracking-tight">Comisiones · {titulo}</h1>
                {liquidacion === null ? (
                    <Alert>
                        <AlertTitle>Cierre trimestral pendiente</AlertTitle>
                        <AlertDescription>Falta aprobar los siguientes meses: {meses_pendientes.join(", ")}.</AlertDescription>
                    </Alert>
                ) : (
                    <>
                        <InsigniaEstado estado={liquidacion.estado} />
                        <PasosLiquidacion liquidacion={liquidacion} conDetalle={permisos.puede_ver_detalle} />
                        {liquidacion.base !== null && <BaseLiquidacion base={liquidacion.base} />}
                        {liquidacion.tipo_periodo === "mensual" && liquidacion.base?.actualizacion_fuente !== undefined && (
                            <AvisoActualizacionFuente fuente={liquidacion.base.actualizacion_fuente} />
                        )}
                        <IndicadoresLiquidacion indicadores={liquidacion.indicadores} />
                        {aviso_separacion && (
                            <Alert>
                                <AlertDescription>{aviso_separacion}</AlertDescription>
                            </Alert>
                        )}
                        <AccionesLiquidacion key={version} liquidacion={liquidacion} permisos={permisos} />
                        <PestanasCargos key={liquidacion.id} liquidacion={liquidacion} />
                        {permisos.puede_ver_detalle && (liquidacion.estado === "aprobada" || liquidacion.estado === "enviada_personal") && (
                            <EnviosPersonal
                                key={version}
                                idLiquidacion={liquidacion.id}
                                version={liquidacion.liquidacion_id}
                                envios={liquidacion.envios}
                                enCurso={envio_en_curso}
                                puedeReenviar={permisos.puede_reenviar}
                            />
                        )}
                        {permisos.puede_ver_detalle && <HistorialLiquidacion historial={liquidacion.historial} />}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
