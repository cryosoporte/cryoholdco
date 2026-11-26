import LaboratorioLayout from "@/layouts/laboratorio-layout";
import { EleccionSede } from "@/components/laboratorio/eleccion-sede";
import { SesionLaboratorio } from "@/components/laboratorio/sesion-laboratorio";
import type { EleccionSedeProps } from "@/components/laboratorio/tipos";

export default function LaboratorioSede(props: EleccionSedeProps) {
    return (
        <LaboratorioLayout
            actions={
                <SesionLaboratorio
                    ruc={props.laboratorio}
                    nombre={props.nombre_laboratorio}
                    disabled={false}
                />
            }
        >
            <div className="mx-auto w-full max-w-lg py-6 sm:py-10">
                <EleccionSede
                    sedes={props.sedes}
                    sede={props.sede}
                    nombreLaboratorio={props.nombre_laboratorio}
                />
            </div>
        </LaboratorioLayout>
    );
}
