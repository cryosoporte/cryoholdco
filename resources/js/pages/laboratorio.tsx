import { useState } from "react";
import { useForm, usePage } from "@inertiajs/react";
import { route } from "ziggy-js";
import LaboratorioLayout from "@/layouts/laboratorio-layout";
import { SelectorSede } from "@/components/laboratorio/selector-sede";
import { SesionLaboratorio } from "@/components/laboratorio/sesion-laboratorio";
import { BuscadorContrato } from "@/components/laboratorio/buscador-contrato";
import { SemaforoToma } from "@/components/laboratorio/semaforo-toma";
import { TomasDelDia } from "@/components/laboratorio/tomas-del-dia";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import type { LaboratorioProps } from "@/components/laboratorio/tipos";

export default function Laboratorio(props: LaboratorioProps) {
    const { errors } = usePage().props;
    const [consultando, setConsultando] = useState(false);
    const [marcando, setMarcando] = useState(false);
    const [resultadoVisible, setResultadoVisible] = useState(true);
    const contexto = useForm({ sede: props.sede ?? "" });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const ocupado = consultando || marcando || contexto.processing;
    const sinSede = props.sede === null;

    return (
        <LaboratorioLayout
            actions={
                <div className="flex w-full flex-wrap items-end gap-3 sm:w-auto">
                    <SelectorSede
                        sedes={props.sedes}
                        sede={props.sede}
                        disabled={ocupado}
                        onCambiar={(sede) => {
                            if (ocupado) {
                                return;
                            }
                            contexto.transform(() => ({ sede }));
                            contexto.post(route("laboratorio.sede"), {
                                ...opcionesPeticion,
                                replace: true,
                                preserveScroll: true,
                            });
                        }}
                    />
                    <SesionLaboratorio
                        ruc={props.laboratorio}
                        nombre={props.nombre_laboratorio}
                        disabled={ocupado}
                    />
                </div>
            }
        >
            <div className="space-y-5">
                <FormError
                    message={
                        errors.sede ??
                        errors.numero_toma ??
                        errorPeticion
                    }
                />
                {sinSede && (
                    <FormError message="No hay una sede activa disponible. No se pueden registrar muestras." />
                )}
                <div
                    className="grid items-start gap-5 lg:grid-cols-3"
                    aria-busy={ocupado}
                >
                    <section className="space-y-5 lg:col-span-2">
                        <BuscadorContrato
                            key={`${props.laboratorio}-${props.sede}`}
                            disabled={
                                marcando || contexto.processing || sinSede
                            }
                            errorCodigo={errors.codigo}
                            onProcesando={(procesando) => {
                                setConsultando(procesando);
                                if (procesando) {
                                    setResultadoVisible(false);
                                }
                            }}
                            onResultado={() => setResultadoVisible(true)}
                        />
                        {props.resultado && resultadoVisible && (
                            <SemaforoToma
                                key={`${props.resultado.numero_contrato}-${props.resultado.numero_toma}`}
                                resultado={props.resultado}
                                disabled={ocupado || sinSede}
                                onProcesando={setMarcando}
                            />
                        )}
                    </section>
                    <TomasDelDia
                        tomas={props.hoy}
                        nombreSede={props.nombre_sede}
                    />
                </div>
            </div>
        </LaboratorioLayout>
    );
}
