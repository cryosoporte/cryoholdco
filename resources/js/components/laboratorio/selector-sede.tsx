import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { OpcionLaboratorio } from "./tipos";

function Selector({
    id,
    titulo,
    opciones,
    valor,
    disabled,
    onChange,
}: {
    id: string;
    titulo: string;
    opciones: OpcionLaboratorio[];
    valor: string | null;
    disabled: boolean;
    onChange: (codigo: string) => void;
}) {
    const items = opciones.map((opcion) => ({
        value: opcion.codigo,
        label: opcion.nombre,
    }));
    return (
        <div className="min-w-36 flex-1 space-y-2">
            <Label htmlFor={id}>{titulo}</Label>
            <Select
                items={items}
                value={valor}
                onValueChange={(codigo) => {
                    if (codigo !== null) {
                        onChange(codigo);
                    }
                }}
                disabled={disabled || opciones.length === 0}
            >
                <SelectTrigger id={id} className="w-full">
                    <SelectValue placeholder={`Sin ${titulo.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    {items.map((opcion) => (
                        <SelectItem key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

export function SelectorSede({
    laboratorios,
    sedes,
    laboratorio,
    sede,
    disabled,
    onCambiar,
}: {
    laboratorios: OpcionLaboratorio[];
    sedes: OpcionLaboratorio[];
    laboratorio: string | null;
    sede: string | null;
    disabled: boolean;
    onCambiar: (laboratorio: string, sede: string | null) => void;
}) {
    return (
        <div className="flex w-full flex-wrap gap-3 sm:w-auto sm:min-w-80">
            <Selector
                id="laboratorio"
                titulo="Laboratorio"
                opciones={laboratorios}
                valor={laboratorio}
                disabled={disabled}
                onChange={(codigo) => onCambiar(codigo, null)}
            />
            <Selector
                id="sede"
                titulo="Sede"
                opciones={sedes}
                valor={sede}
                disabled={disabled}
                onChange={(codigo) => {
                    if (laboratorio !== null) {
                        onCambiar(laboratorio, codigo);
                    }
                }}
            />
        </div>
    );
}
