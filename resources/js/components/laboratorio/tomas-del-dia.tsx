import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import type { TomaDelDia } from "./tipos";

export function TomasDelDia({
    tomas,
    nombreSede,
}: {
    tomas: TomaDelDia[];
    nombreSede: string | null;
}) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>
                    Hoy · {nombreSede ?? "sin sede"}
                </CardDescription>
                <CardTitle>
                    <h2>Muestras tomadas</h2>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {tomas.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Aún no hay muestras tomadas hoy en esta sede.
                    </p>
                ) : (
                    <>
                        <ul className="divide-y">
                            {tomas.map((toma) => (
                                <li
                                    key={`${toma.numero_contrato}-${toma.numero_toma}`}
                                    className="flex items-baseline gap-3 py-3 first:pt-0"
                                >
                                    <span className="font-mono text-sm font-semibold tabular-nums">
                                        {toma.hora}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {toma.nombre ??
                                                "Sin nombre registrado"}
                                        </p>
                                        <p className="truncate font-mono text-xs text-muted-foreground">
                                            {toma.numero_contrato}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        <p className="border-t pt-3 text-sm font-medium">
                            {tomas.length}{" "}
                            {tomas.length === 1 ? "muestra" : "muestras"} hoy
                        </p>
                    </>
                )}
                <p className="border-t pt-4 text-xs text-muted-foreground">
                    Esta pantalla solo muestra el nombre y el semáforo. No
                    muestra teléfono, fecha de parto, clínica ni resultados.
                </p>
            </CardContent>
        </Card>
    );
}
