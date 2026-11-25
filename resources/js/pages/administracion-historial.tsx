import { useState } from "react";
import { TablaHistorial } from "@/components/administracion/tabla-historial";
import type { EventoSeguridad } from "@/components/administracion/tipos";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import AppLayout from "@/layouts/app-layout";

export default function AdministracionHistorial({
    eventos,
    tipos,
}: {
    eventos: EventoSeguridad[];
    tipos: Record<string, string>;
}) {
    const [filtro, setFiltro] = useState("todos");
    const opciones = [
        { value: "todos", label: "Todas las acciones" },
        ...Object.entries(tipos).map(([value, label]) => ({ value, label })),
    ];
    const visibles = filtro === "todos" ? eventos : eventos.filter(({ tipo }) => tipo === filtro);

    return (
        <AppLayout title="Administración - Historial">
            <div className="space-y-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Historial</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Últimos 200 eventos en hora de Lima.</p>
                    </div>
                    <Select items={opciones} value={filtro} onValueChange={(valor) => {
                        if (valor !== null) setFiltro(valor);
                    }}>
                        <SelectTrigger className="w-full sm:w-52" aria-label="Filtrar por tipo de acción">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {opciones.map(({ value, label }) => <SelectItem key={value} value={value}>{label}</SelectItem>)}
                        </SelectContent>
                    </Select>
                </div>
                <TablaHistorial
                    eventos={visibles}
                    mensajeVacio={eventos.length === 0 ? "Aún no hay eventos registrados." : "No hay eventos de este tipo."}
                />
            </div>
        </AppLayout>
    );
}
