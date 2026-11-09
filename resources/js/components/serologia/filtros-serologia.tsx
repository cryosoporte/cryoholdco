import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

import type { Asesor, EstadoSerologia } from "./tipos";

type FiltrosSerologiaProps = {
    texto: string;
    asesorSeleccionado: string | null;
    estadoSeleccionado: EstadoSerologia | null;
    asesores: Asesor[];
    onCambiarTexto: (texto: string) => void;
    onCambiarAsesor: (asesor: string | null) => void;
    onLimpiarEstado: () => void;
};

export function FiltrosSerologia({
    texto,
    asesorSeleccionado,
    estadoSeleccionado,
    asesores,
    onCambiarTexto,
    onCambiarAsesor,
    onLimpiarEstado,
}: FiltrosSerologiaProps) {
    const opcionesAsesor = [
        { value: "__todos__", label: "Todos los asesores" },
        ...asesores.map((opcion) => ({
            value: opcion.dni,
            label: opcion.nombre ?? opcion.dni,
        })),
    ];

    return (
        <div className="flex flex-wrap items-center gap-3">
            <Input
                aria-label="Buscar clientes"
                placeholder="Buscar por nombre, DNI o código"
                value={texto}
                onChange={(event) => onCambiarTexto(event.target.value)}
                className="max-w-md"
                maxLength={100}
            />

            <Select
                items={opcionesAsesor}
                value={asesorSeleccionado ?? "__todos__"}
                onValueChange={(valor) =>
                    onCambiarAsesor(valor === "__todos__" ? null : valor)
                }
            >
                <SelectTrigger
                    aria-label="Filtrar por asesor"
                    className="w-full sm:w-64"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {opcionesAsesor.map((opcion) => (
                        <SelectItem key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {estadoSeleccionado && (
                <Button variant="outline" onClick={onLimpiarEstado}>
                    Ver todos
                </Button>
            )}
        </div>
    );
}
