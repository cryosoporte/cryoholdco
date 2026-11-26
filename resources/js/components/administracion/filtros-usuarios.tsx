import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
type FiltrosUsuariosProps = {
    busqueda: string;
    onCambiarBusqueda: (busqueda: string) => void;
    filtroRol: string;
    onCambiarRol: (rol: string) => void;
    opcionesRol: { value: string; label: string }[];
    totalVisible: number;
    totalPersonas: number;
};

export function FiltrosUsuarios({
    busqueda,
    onCambiarBusqueda,
    filtroRol,
    onCambiarRol,
    opcionesRol,
    totalVisible,
    totalPersonas,
}: FiltrosUsuariosProps) {
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
                    items={opcionesRol}
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
                        {opcionesRol.map(({ value, label }) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <p role="status" className="text-sm text-muted-foreground">
                Mostrando {totalVisible} de {totalPersonas} personas
            </p>
        </div>
    );
}
