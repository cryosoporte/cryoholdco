import { useCallback, useEffect, useRef, useState } from "react";
import { router, useHttp } from "@inertiajs/react";
import { route } from "ziggy-js";
import { toast } from "@/components/ui/sonner";

import { mensajeDeError } from "./errores";
import type {
    ArchivoDocumento,
    ContratoInforme,
    MotivoNoApto,
    MotivosElegidos,
    Titular,
    VistaPreviaCorreo,
} from "./tipos";

type RespuestaDocumentos = {
    archivos: ArchivoDocumento[];
    titulares: Titular[];
    motivos: MotivoNoApto[];
};

export function useDocumentosContrato(contrato: ContratoInforme) {
    const lista = useHttp<{ numero_base: string }, RespuestaDocumentos>({
        numero_base: "",
    });
    const previa = useHttp<
        { numero_base: string; motivo_sangre?: string; motivo_tejido?: string },
        VistaPreviaCorreo
    >({ numero_base: "" });
    const envio = useHttp<
        {
            numero_base: string;
            identificacion: string;
            archivos: string[];
            motivo_sangre?: string;
            motivo_tejido?: string;
        },
        { message: string }
    >({ numero_base: "", identificacion: "", archivos: [] });
    const envioEnCurso = useRef(false);
    const consultaVigente = useRef(0);
    const [archivos, setArchivos] = useState<ArchivoDocumento[]>([]);
    const [titulares, setTitulares] = useState<Titular[]>([]);
    const [opcionesMotivo, setOpcionesMotivo] = useState<MotivoNoApto[]>([]);
    const [motivos, setMotivos] = useState<MotivosElegidos>({});
    const [vistaPrevia, setVistaPrevia] = useState<VistaPreviaCorreo | null>(
        null,
    );
    const [seleccionados, setSeleccionados] = useState<string[]>([]);
    const [cargando, setCargando] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const cargar = useCallback(async (): Promise<void> => {
        if (!contrato.numero_base) {
            return;
        }

        const consulta = ++consultaVigente.current;
        const numeroBase = contrato.numero_base;

        setCargando(true);
        setError(null);
        lista.transform(() => ({ numero_base: numeroBase }));

        try {
            await lista.get(route("informes.documentos"), {
                onSuccess: (respuesta) => {
                    if (consulta === consultaVigente.current) {
                        setArchivos(respuesta.archivos);
                        setTitulares(respuesta.titulares);
                        setOpcionesMotivo(respuesta.motivos);
                        setSeleccionados([]);
                    }
                },
                onError: (errores) => {
                    if (consulta === consultaVigente.current) {
                        setError(Object.values(errores)[0] ?? "No se pudieron cargar los documentos.");
                    }
                },
            });
        } catch (fallo) {
            if (consulta === consultaVigente.current) {
                setError(
                    mensajeDeError(
                        fallo,
                        "No se pudieron cargar los documentos. Revisa la conexión e intenta de nuevo.",
                    ),
                );
            }
        } finally {
            if (consulta === consultaVigente.current) {
                setCargando(false);
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [contrato.numero_base]);

    useEffect(() => {
        void cargar();

        return () => {
            consultaVigente.current++;
        };
    }, [cargar]);

    function alternar(nombre: string, marcado: boolean): void {
        setSeleccionados((actuales) =>
            marcado
                ? [...actuales, nombre]
                : actuales.filter((actual) => actual !== nombre),
        );
    }

    function elegirMotivo(servicio: "sangre" | "tejido", motivo: string): void {
        setMotivos((actuales) => ({ ...actuales, [servicio]: motivo }));
    }

    function motivosDelEnvio(): {
        motivo_sangre?: string;
        motivo_tejido?: string;
    } {
        return {
            ...(contrato.sangre === "NO APTO" && motivos.sangre
                ? { motivo_sangre: motivos.sangre }
                : {}),
            ...(contrato.tejido === "NO APTO" && motivos.tejido
                ? { motivo_tejido: motivos.tejido }
                : {}),
        };
    }

    async function verVistaPrevia(): Promise<void> {
        if (!contrato.numero_base) {
            return;
        }

        const numeroBase = contrato.numero_base;
        const elegidos = motivosDelEnvio();

        previa.transform(() => ({ numero_base: numeroBase, ...elegidos }));

        try {
            await previa.get(route("informes.documentos.vista-previa"), {
                onSuccess: (respuesta) => setVistaPrevia(respuesta),
                onError: (errores) => {
                    toast.error(
                        Object.values(errores)[0] ??
                            "No se pudo generar la vista previa.",
                    );
                },
            });
        } catch (fallo) {
            toast.error(
                mensajeDeError(
                    fallo,
                    "No se pudo generar la vista previa. Revisa la conexión e intenta de nuevo.",
                ),
            );
        }
    }

    async function enviar(): Promise<void> {
        if (envioEnCurso.current || seleccionados.length === 0 || !contrato.numero_base) {
            return;
        }

        envioEnCurso.current = true;
        envio.transform(() => ({
            numero_base: contrato.numero_base ?? "",
            identificacion: contrato.identificacion,
            archivos: seleccionados,
            ...motivosDelEnvio(),
        }));

        try {
            await envio.post(route("informes.documentos.enviar"), {
                onSuccess: (respuesta) => {
                    toast.success(respuesta.message, {
                        description: contrato.familia ?? contrato.nombre,
                    });
                    setSeleccionados([]);
                    router.reload({ only: ["resultados", "resumen"] });
                },
                onError: (errores) => {
                    toast.error(Object.values(errores)[0] ?? "No se pudieron enviar los documentos.");
                },
            });
        } catch (fallo) {
            toast.error(
                mensajeDeError(
                    fallo,
                    "No se pudo confirmar el envío de los documentos. Revisa la conexión e intenta de nuevo más tarde.",
                ),
            );
        } finally {
            envioEnCurso.current = false;
        }
    }

    return {
        archivos,
        titulares,
        opcionesMotivo,
        motivos,
        vistaPrevia,
        cerrarVistaPrevia: () => setVistaPrevia(null),
        generandoVistaPrevia: previa.processing,
        seleccionados,
        cargando,
        error,
        enviando: envio.processing,
        cargar,
        alternar,
        elegirMotivo,
        verVistaPrevia,
        enviar,
    };
}
