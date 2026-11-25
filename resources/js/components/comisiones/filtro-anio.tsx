import { useState } from "react";
import { router } from "@inertiajs/react";
import { route } from "ziggy-js";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";

const TODOS = "todos";

export function FiltroAnio({ anio, anios }: { anio: string; anios: string[] }) {
    const [cargando, setCargando] = useState<string | null>(null);
    const opciones = [...anios.map((valor) => ({ value: valor, label: valor })), { value: TODOS, label: "Todos" }];

    function cambiarAnio(valor: string | null): void {
        if (valor === null || valor === anio || cargando !== null) {
            return;
        }

        router.get(route("reportes.comisiones"), { anio: valor }, {
            only: ["liquidaciones", "anio"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setCargando(valor),
            onFinish: () => setCargando(null),
        });
    }

    return (
        <div className="flex items-center gap-2" aria-busy={cargando !== null}>
            {cargando !== null && <Spinner className="text-muted-foreground" aria-label="Cargando liquidaciones" />}
            <Select items={opciones} value={cargando ?? anio} onValueChange={cambiarAnio} disabled={cargando !== null}>
                <SelectTrigger aria-label="Filtrar por año" className="w-32">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {opciones.map(({ value, label }) => <SelectItem key={value} value={value}>{label}</SelectItem>)}
                </SelectContent>
            </Select>
        </div>
    );
}
