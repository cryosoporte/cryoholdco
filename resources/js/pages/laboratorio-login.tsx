import LaboratorioLayout from "@/layouts/laboratorio-layout";
import { FormularioLogin } from "@/components/laboratorio/formulario-login";

export default function LaboratorioLogin() {
    return (
        <LaboratorioLayout>
            <div className="mx-auto w-full max-w-sm py-6 sm:py-10">
                <FormularioLogin />
            </div>
        </LaboratorioLayout>
    );
}
