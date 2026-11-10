import { Badge } from "@/components/ui/badge";

export type EstadoPrueba = "pendiente" | "tomada" | "recibido" | "sin_registro";

export const presentacionEstado = {
    pendiente: {
        texto: "Pendiente",
        clase: "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200",
    },
    tomada: {
        texto: "Tomada",
        clase: "border-criocord-200 bg-criocord-50 text-criocord-900 dark:border-criocord-700 dark:bg-criocord-900 dark:text-criocord-100",
    },
    recibido: {
        texto: "Resultado recibido",
        clase: "border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200",
    },
    sin_registro: {
        texto: "Sin registro",
        clase: "border-border bg-muted text-muted-foreground",
    },
} satisfies Record<EstadoPrueba, { texto: string; clase: string }>;

export function EstadoPruebaBadge({ estado }: { estado: EstadoPrueba }) {
    const configuracion = presentacionEstado[estado];
    return (
        <Badge variant="outline" className={configuracion.clase}>
            {configuracion.texto}
        </Badge>
    );
}
