import type { CodigoCargo, EstadoLiquidacion, EventoLiquidacion } from "./tipos";

export const estados = {
    borrador: {
        etiqueta: "Borrador",
        clase: "border-border bg-muted text-muted-foreground",
        ayuda: "Pendiente de revisión por el analista.",
    },
    enviada: {
        etiqueta: "Enviada a gerencia",
        clase: "border-criocord-500/30 bg-criocord-500/10 text-criocord-700 dark:text-criocord-300",
        ayuda: "Pendiente de aprobación por gerencia.",
    },
    rechazada: {
        etiqueta: "Devuelta",
        clase: "border-amber-600/30 bg-amber-500/10 text-amber-800 dark:text-amber-300",
        ayuda: "Gerencia devolvió la liquidación con un comentario.",
    },
    aprobada: {
        etiqueta: "Aprobada",
        clase: "border-emerald-600/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300",
        ayuda: "Gerencia aprobó el período. Los envíos al personal están en curso.",
    },
    enviada_personal: {
        etiqueta: "Envíos al personal",
        clase: "border-emerald-600/30 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300",
        ayuda: "El período está cerrado. Consulta el resultado de los envíos.",
    },
} satisfies Record<EstadoLiquidacion, { etiqueta: string; clase: string; ayuda: string }>;

export const pasos = {
    generada: "Generada",
    procesada: "Procesada",
    aprobada: "Aprobada",
    enviada_personal: "Enviada al personal",
} as const;

export const eventos = {
    ...pasos,
    rechazada: "Devuelta por gerencia",
    reenvio: "Reenvío de correos",
} satisfies Record<EventoLiquidacion["tipo"], string>;

export const ordenCargos: CodigoCargo[] = ["EJEC", "LIMA", "PROV", "ANUAL", "OPE", "MKT"];
