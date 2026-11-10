import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import type { SedeCliente } from "./tipos";

export function SedesDisponibles({ sedes }: { sedes: SedeCliente[] }) {
    const grupos = new Map<string, SedeCliente[]>();
    for (const sede of sedes) {
        grupos.set(sede.laboratorio, [
            ...(grupos.get(sede.laboratorio) ?? []),
            sede,
        ]);
    }
    return (
        <Card>
            <CardHeader>
                <CardTitle>Sedes disponibles</CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
                {Array.from(grupos, ([laboratorio, opciones]) => (
                    <div key={laboratorio} className="space-y-2">
                        <h3 className="font-medium">{laboratorio}</h3>
                        <ul className="flex flex-wrap gap-2">
                            {opciones?.map((sede) => (
                                <li key={`${sede.codigo}-${sede.nombre}`}>
                                    <Badge variant="secondary">
                                        {sede.nombre}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
                <p className="border-t pt-4 text-xs text-muted-foreground">
                    Ve a la que te quede más cerca con tu DNI. No necesitas
                    cita.
                </p>
            </CardContent>
        </Card>
    );
}
