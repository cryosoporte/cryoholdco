import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

type FiltrosInformesProps = {
    texto: string;
    envioSeleccionado: string | null;
    onCambiarTexto: (texto: string) => void;
    onCambiarEnvio: (envio: string | null) => void;
};

const opcionesEnvio = [
    { value: "__todos__", label: "Enviados y no enviados" },
    { value: "enviado", label: "Enviados" },
    { value: "pendiente", label: "No enviados" },
];

export function FiltrosInformes({
    texto,
    envioSeleccionado,
    onCambiarTexto,
    onCambiarEnvio,
}: FiltrosInformesProps) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <Input
                aria-label="Buscar contratos"
                placeholder="Buscar por familia, nombre, DNI o contrato"
                value={texto}
                onChange={(event) => onCambiarTexto(event.target.value)}
                className="max-w-md"
                maxLength={100}
            />

            <Select
                items={opcionesEnvio}
                value={envioSeleccionado ?? "__todos__"}
                onValueChange={(valor) =>
                    onCambiarEnvio(valor === "__todos__" ? null : valor)
                }
            >
                <SelectTrigger
                    aria-label="Filtrar por envío de documentos"
                    className="w-full sm:w-56"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {opcionesEnvio.map((opcion) => (
                        <SelectItem key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
