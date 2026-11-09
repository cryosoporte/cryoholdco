import type { EstadoSerologia } from "./tipos";

export const opcionesEstado: EstadoSerologia[] = [
    "por_vencer",
    "pendiente",
    "tomada",
    "recibido",
];

export const estados = {
    por_vencer: {
        texto: "Por vencer",
        clase: "bg-red-50 text-red-700 border-red-700",
        descripcion: "7 días o menos",
        borde: "border-l-red-500",
        seleccion: "ring-red-600 bg-red-50 dark:bg-red-950/30",
    },
    recibido: {
        texto: "Resultado recibido",
        clase: "bg-green-50 text-green-700 border-green-700",
        descripcion: "Proceso completo",
        borde: "border-l-green-600",
        seleccion: "ring-green-600 bg-green-50 dark:bg-green-950/30",
    },
    tomada: {
        texto: "Tomada",
        clase: "bg-blue-50 text-blue-700 border-blue-700",
        descripcion: "Esperando resultado",
        borde: "border-l-blue-500",
        seleccion: "ring-blue-600 bg-blue-50 dark:bg-blue-950/30",
    },
    pendiente: {
        texto: "Pendiente",
        clase: "bg-yellow-50 text-yellow-700 border-yellow-700",
        descripcion: "Aún sin toma registrada",
        borde: "border-l-yellow-500",
        seleccion: "ring-yellow-600 bg-yellow-50 dark:bg-yellow-950/30",
    },
};

export const avisosPlazo = {
    sin_fecha: null,
    en_plazo: null,
    tomada: null,
    recibido: null,
    aun_no_abre: {
        texto: "Aún no abre",
        clase: "bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-200",
    },
    por_vencer: {
        texto: "Por vencer",
        clase: "bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-950 dark:text-orange-200",
    },
    fuera_de_plazo: {
        texto: "Fuera de plazo",
        clase: "bg-red-100 text-red-800 border-red-200 dark:bg-red-950 dark:text-red-200",
    },
};
