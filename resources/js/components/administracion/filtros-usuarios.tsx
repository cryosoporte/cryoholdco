import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { opcionesRol, type ConteosPorRol, type FiltroRolUsuario } from "./tipos";

const filtrosRol = [
    { value: "todos", label: "Todos" },
    opcionesRol.ADMINISTRADOR,
    opcionesRol.GERENCIA,
    opcionesRol["sin-rol"],
] as const;

type FiltrosUsuariosProps = {
    busqueda: string;
    onCambiarBusqueda: (busqueda: string) => void;
    filtroRol: FiltroRolUsuario;
    onCambiarRol: (rol: FiltroRolUsuario) => void;
    conteosPorRol: ConteosPorRol;
    totalVisible: number;
};

export function FiltrosUsuarios({
    busqueda,
    onCambiarBusqueda,
    filtroRol,
    onCambiarRol,
    conteosPorRol,
    totalVisible,
}: FiltrosUsuariosProps) {
    const opciones = filtrosRol.map(({ value, label }) => ({
        value,
        label: `${label} (${conteosPorRol[value]})`,
    }));

    return (
        <div className="space-y-2">
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    type="search"
                    aria-label="Buscar por nombre, correo o DNI"
                    placeholder="Buscar por nombre, correo o DNI"
                    className="sm:max-w-sm"
                    value={busqueda}
                    onChange={(evento) => onCambiarBusqueda(evento.target.value)}
                />
                <Select
                    items={opciones}
                    value={filtroRol}
                    onValueChange={(rol) => {
                        if (rol !== null) {
                            onCambiarRol(rol);
                        }
                    }}
                >
                    <SelectTrigger
                        aria-label="Filtrar por rol"
                        className="w-full sm:w-44"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {opciones.map(({ value, label }) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <p role="status" className="text-sm text-muted-foreground">
                Mostrando {totalVisible} de {conteosPorRol.todos} personas
            </p>
        </div>
    );
}
