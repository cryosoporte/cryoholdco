import { Field, FieldLabel } from "@/components/ui/field";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { OpcionLaboratorio } from "./tipos";

/**
 * La sede desde donde trabaja el mostrador, entre las sedes activas del laboratorio.
 * Solo avisa cuando se elige una sede distinta de la actual.
 */
export function SelectorSede({
    sedes,
    sede,
    disabled,
    onCambiar,
}: {
    sedes: OpcionLaboratorio[];
    sede: string | null;
    disabled: boolean;
    onCambiar: (sede: string) => void;
}) {
    const items = sedes.map((opcion) => ({
        value: opcion.codigo,
        label: opcion.nombre,
    }));

    return (
        <Field className="w-full sm:w-64">
            <FieldLabel htmlFor="sede">Sede</FieldLabel>
            <Select
                items={items}
                value={sede}
                onValueChange={(codigo) => {
                    if (codigo !== null && codigo !== sede) {
                        onCambiar(codigo);
                    }
                }}
                disabled={disabled || sedes.length === 0}
            >
                <SelectTrigger id="sede" className="w-full">
                    <SelectValue placeholder="Sin sede" />
                </SelectTrigger>
                <SelectContent>
                    {items.map((opcion) => (
                        <SelectItem key={opcion.value} value={opcion.value}>
                            {opcion.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}
