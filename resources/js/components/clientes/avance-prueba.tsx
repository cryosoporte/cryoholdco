import { CheckIcon } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { formatearFecha } from "@/lib/fechas";
import type { Cliente } from "./tipos";

export function AvancePrueba({ cliente }: { cliente: Cliente }) {
    const pasos = [
        {
            titulo: "Contrato firmado",
            fecha: cliente.fecha_contrato,
            completo: true,
        },
        {
            titulo: "Prueba tomada",
            fecha: cliente.fecha_toma,
            completo: cliente.fecha_toma !== null,
        },
        {
            titulo: "Examen recibido",
            fecha: cliente.fecha_resultado,
            completo: cliente.fecha_resultado !== null,
        },
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Tu avance</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <ol className="divide-y">
                    {pasos.map((paso) => (
                        <li
                            key={paso.titulo}
                            className="flex items-center gap-3 py-3 first:pt-0"
                        >
                            <span
                                className={`grid size-5 shrink-0 place-items-center rounded-full border ${paso.completo ? "border-primary bg-primary text-primary-foreground" : "bg-muted"}`}
                                aria-hidden="true"
                            >
                                {paso.completo && (
                                    <CheckIcon className="size-3" />
                                )}
                            </span>
                            <span className="flex-1 text-sm">
                                {paso.titulo}
                            </span>
                            <span className="text-xs tabular-nums text-muted-foreground">
                                {paso.fecha
                                    ? formatearFecha(paso.fecha)
                                    : "Pendiente"}
                            </span>
                        </li>
                    ))}
                </ol>
                <Separator />
                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">
                            Fecha de parto
                        </dt>
                        <dd className="mt-1 font-medium">
                            {cliente.fecha_nacimiento
                                ? formatearFecha(cliente.fecha_nacimiento)
                                : "Sin registrar"}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            Sede de la toma
                        </dt>
                        <dd className="mt-1 font-medium">
                            {cliente.sede_toma ?? "Sin registrar"}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}
