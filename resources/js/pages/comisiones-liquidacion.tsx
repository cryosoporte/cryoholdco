import { Link, useForm } from "@inertiajs/react";
import { RotateCcwIcon } from "lucide-react";
import { route } from "ziggy-js";
import { AccionesLiquidacion } from "@/components/comisiones/acciones-liquidacion";
import { BaseLiquidacion } from "@/components/comisiones/base-liquidacion";
import { EnviosPersonal } from "@/components/comisiones/envios-personal";
import { HistorialLiquidacion } from "@/components/comisiones/historial-liquidacion";
import { IndicadoresLiquidacion } from "@/components/comisiones/indicadores-liquidacion";
import { InsigniaEstado } from "@/components/comisiones/insignia-estado";
import { PasosLiquidacion } from "@/components/comisiones/pasos-liquidacion";
import { PestanasCargos } from "@/components/comisiones/pestanas-cargos";
import { SelectorRol } from "@/components/comisiones/selector-rol";
import type { Liquidacion, PermisosDemo, RolDemo } from "@/components/comisiones/tipos";
import { useAccionComisiones } from "@/components/comisiones/use-accion-comisiones";
import { FormError } from "@/components/form-error";
import { AvisoActualizacionFuente } from "@/components/reportes/aviso-actualizacion-fuente";
import type { ActualizacionFuente } from "@/components/reportes/tipos";
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from "@/components/ui/breadcrumb";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import AppLayout from "@/layouts/app-layout";

export default function ComisionesLiquidacion({ liquidacion, rol, permisos, fuente }: {
    liquidacion: Liquidacion;
    rol: RolDemo;
    permisos: PermisosDemo;
    fuente: ActualizacionFuente | null;
}) {
    const reinicio = useForm({});
    const { errorPeticion, opciones } = useAccionComisiones();

    return (
        <AppLayout title={`Comisiones - ${liquidacion.periodo}`}>
            <div className="min-w-0 space-y-6">
                <Breadcrumb aria-label="Ruta de navegación">
                    <BreadcrumbList>
                        <BreadcrumbItem><BreadcrumbLink render={<Link href={route("reportes")} />}>Reportes</BreadcrumbLink></BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem><BreadcrumbLink render={<Link href={route("reportes.comisiones")} />}>Comisiones</BreadcrumbLink></BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem><BreadcrumbPage>{liquidacion.periodo}</BreadcrumbPage></BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight">Comisiones · {liquidacion.periodo}</h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <InsigniaEstado estado={liquidacion.estado} />
                            {!liquidacion.es_demo_activa && <span className="text-xs text-muted-foreground">Solo lectura</span>}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-start gap-3">
                        <SelectorRol rol={rol} />
                        <Button type="button" variant="outline" disabled={reinicio.processing}
                            onClick={() => reinicio.post(route("reportes.comisiones.demo.reiniciar"), opciones)}>
                            {reinicio.processing ? <Spinner aria-hidden="true" /> : <RotateCcwIcon aria-hidden="true" />}
                            {reinicio.processing ? "Reiniciando…" : "Reiniciar demo"}
                        </Button>
                    </div>
                </div>
                <FormError message={errorPeticion} />
                <PasosLiquidacion liquidacion={liquidacion} />
                <div className="space-y-3">
                    <BaseLiquidacion base={liquidacion.base} />
                    <AvisoActualizacionFuente fuente={fuente} />
                </div>
                <IndicadoresLiquidacion indicadores={liquidacion.indicadores} />
                <AccionesLiquidacion liquidacion={liquidacion} rol={rol} permisos={permisos} />
                <PestanasCargos key={liquidacion.id} liquidacion={liquidacion} />
                {(liquidacion.estado === "aprobada" || liquidacion.estado === "enviada_personal") && (
                    <EnviosPersonal
                        idLiquidacion={liquidacion.id}
                        envios={liquidacion.envios}
                        puedeReenviar={permisos.puede_reenviar}
                    />
                )}
                <HistorialLiquidacion historial={liquidacion.historial} />
            </div>
        </AppLayout>
    );
}
