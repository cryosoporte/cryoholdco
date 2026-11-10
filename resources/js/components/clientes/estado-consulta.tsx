import { CircleCheckIcon, ClockIcon, CircleAlertIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
    presentacionEstado,
    type EstadoPrueba,
} from "@/components/serologia/estado-prueba";

const mensajes = {
    recibido: {
        titulo: "Examen recibido",
        mensaje:
            "Criocord ya tiene tu resultado. Tu asesor(a) te contactará si hace falta algo más.",
        icono: CircleCheckIcon,
    },
    tomada: {
        titulo: "Prueba tomada",
        mensaje: "Estamos esperando que el laboratorio nos envíe tu resultado.",
        icono: ClockIcon,
    },
    pendiente: {
        titulo: "Falta tomarte la prueba",
        mensaje:
            "Acércate con tu DNI a cualquiera de las sedes. No tiene costo: está incluido en tu contrato.",
        icono: CircleAlertIcon,
    },
    sin_registro: {
        titulo: "Estamos actualizando tus datos",
        mensaje:
            "Tu contrato todavía no está en el nuevo registro de pruebas. Escríbenos por WhatsApp y te decimos en qué va.",
        icono: CircleAlertIcon,
    },
};

export function EstadoConsulta({ estado }: { estado: EstadoPrueba }) {
    const { titulo, mensaje, icono: Icono } = mensajes[estado];
    return (
        <Alert role="status" className={presentacionEstado[estado].clase}>
            <Icono />
            <AlertTitle>{titulo}</AlertTitle>
            <AlertDescription>{mensaje}</AlertDescription>
        </Alert>
    );
}
