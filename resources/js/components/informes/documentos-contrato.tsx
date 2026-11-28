import { route } from "ziggy-js";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Spinner } from "@/components/ui/spinner";

import type { ContratoInforme, Titular } from "./tipos";
import { useDocumentosContrato } from "./use-documentos-contrato";

type DocumentosContratoProps = {
    contrato: ContratoInforme | null;
    onCerrar: () => void;
};

type Servicio = "sangre" | "tejido";

const NOMBRES_SERVICIO: Record<Servicio, string> = {
    sangre: "SANGRE",
    tejido: "TEJIDO",
};

function formatearTamano(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function TarjetaTitular({ titular }: { titular: Titular }) {
    return (
        <div className="rounded-md border p-3 text-sm">
            <p className="text-xs font-medium uppercase text-muted-foreground">
                Titular {titular.titular}
            </p>
            <p className="font-semibold">{titular.nombre}</p>
            <p>Celular: {titular.celular ?? "—"}</p>
            <p className="break-words">Correo: {titular.correo ?? "—"}</p>
        </div>
    );
}

function ListaDocumentos({
    archivos,
    seleccionados,
    cargando,
    error,
    enviando,
    numeroBase,
    onReintentar,
    onAlternar,
}: {
    archivos: { nombre: string; tamano: number }[];
    seleccionados: string[];
    cargando: boolean;
    error: string | null;
    enviando: boolean;
    numeroBase: string;
    onReintentar: () => void;
    onAlternar: (nombre: string, marcado: boolean) => void;
}) {
    return (
        <section className="space-y-3">
            <h3 className="text-sm font-semibold">
                Listado de Documentos - Fuente: DISCO I
            </h3>

            {cargando && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Spinner /> Buscando documentos…
                </p>
            )}

            {!cargando && error && (
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <p className="text-destructive">{error}</p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="cursor-pointer"
                        onClick={onReintentar}
                    >
                        Reintentar
                    </Button>
                </div>
            )}

            {!cargando && !error && archivos.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    La carpeta del contrato no tiene archivos.
                </p>
            )}

            {!cargando && !error && archivos.length > 0 && (
                <ul className="divide-y rounded-md border">
                    {archivos.map((archivo) => (
                        <li
                            key={archivo.nombre}
                            className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-muted/50"
                        >
                            <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <Checkbox
                                    checked={seleccionados.includes(archivo.nombre)}
                                    disabled={enviando}
                                    onCheckedChange={(marcado) =>
                                        onAlternar(archivo.nombre, marcado)
                                    }
                                />
                                <span className="min-w-0 flex-1 break-words">
                                    {archivo.nombre}
                                </span>
                            </label>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatearTamano(archivo.tamano)}
                            </span>
                            <a
                                href={route("informes.documentos.ver", {
                                    numero_base: numeroBase,
                                    archivo: archivo.nombre,
                                })}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="shrink-0 text-xs font-medium text-criocord-600 underline-offset-4 hover:underline"
                                aria-label={`Ver ${archivo.nombre}`}
                            >
                                Ver
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function DetalleContrato({ contrato }: { contrato: ContratoInforme }) {
    const {
        archivos,
        titulares,
        opcionesMotivo,
        motivos,
        vistaPrevia,
        cerrarVistaPrevia,
        generandoVistaPrevia,
        seleccionados,
        cargando,
        error,
        enviando,
        cargar,
        alternar,
        elegirMotivo,
        verVistaPrevia,
        enviar,
    } = useDocumentosContrato(contrato);

    const servicios = (["sangre", "tejido"] as const).filter(
        (servicio) => contrato[servicio] !== null,
    );
    const sinLiberar = servicios.some(
        (servicio) => contrato[servicio] === "NO LIBERADO",
    );
    const faltaMotivo = servicios.some(
        (servicio) => contrato[servicio] === "NO APTO" && !motivos[servicio],
    );
    const correoNoDisponible = sinLiberar || faltaMotivo;

    return (
        <div className="grid gap-6 md:grid-cols-2">
            <div className="space-y-4">
                {titulares.map((titular) => (
                    <TarjetaTitular key={titular.titular} titular={titular} />
                ))}

                <section className="space-y-3 rounded-md border p-3 text-sm">
                    <p className="font-semibold">
                        RESULTADO{contrato.estado ? ` · ${contrato.estado}` : ""}:
                    </p>

                    <ul className="space-y-3">
                        {servicios.map((servicio) => (
                            <li key={servicio} className="space-y-2">
                                <p className="font-medium">
                                    • {NOMBRES_SERVICIO[servicio]}{" "}
                                    {contrato[servicio]}
                                </p>

                                {contrato[servicio] === "NO APTO" && (
                                    <fieldset
                                        className="space-y-1 pl-4"
                                        disabled={enviando}
                                    >
                                        <legend className="sr-only">
                                            Motivo de {NOMBRES_SERVICIO[servicio]} no apto
                                        </legend>
                                        {opcionesMotivo.map((opcion) => (
                                            <label
                                                key={opcion.valor}
                                                className="flex cursor-pointer items-center gap-2"
                                            >
                                                <input
                                                    type="radio"
                                                    name={`motivo-${servicio}`}
                                                    value={opcion.valor}
                                                    checked={
                                                        motivos[servicio] === opcion.valor
                                                    }
                                                    onChange={() =>
                                                        elegirMotivo(servicio, opcion.valor)
                                                    }
                                                />
                                                <span>{opcion.etiqueta}</span>
                                            </label>
                                        ))}
                                    </fieldset>
                                )}
                            </li>
                        ))}
                    </ul>

                    {sinLiberar && (
                        <p className="text-destructive">
                            Un servicio aún no está liberado: no se puede enviar el
                            informe.
                        </p>
                    )}
                    {faltaMotivo && !sinLiberar && (
                        <p className="text-muted-foreground">
                            Elige el motivo del resultado NO APTO para el correo.
                        </p>
                    )}
                </section>

                <div className="flex flex-wrap justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="cursor-pointer"
                        disabled={
                            correoNoDisponible || generandoVistaPrevia || enviando
                        }
                        onClick={() => void verVistaPrevia()}
                    >
                        {generandoVistaPrevia ? "Generando…" : "Vista previa del correo"}
                    </Button>
                    <Button
                        type="button"
                        className="cursor-pointer"
                        disabled={
                            correoNoDisponible ||
                            seleccionados.length === 0 ||
                            enviando
                        }
                        onClick={() => void enviar()}
                    >
                        {enviando
                            ? "Enviando…"
                            : seleccionados.length === 0
                              ? "Enviar por correo"
                              : `Enviar ${seleccionados.length} ${seleccionados.length === 1 ? "archivo" : "archivos"} por correo`}
                    </Button>
                </div>
            </div>

            <ListaDocumentos
                archivos={archivos}
                seleccionados={seleccionados}
                cargando={cargando}
                error={error}
                enviando={enviando}
                numeroBase={contrato.numero_base}
                onReintentar={() => void cargar()}
                onAlternar={alternar}
            />

            <Dialog
                open={vistaPrevia !== null}
                onOpenChange={(abierto) => {
                    if (!abierto) {
                        cerrarVistaPrevia();
                    }
                }}
            >
                <DialogContent className="max-w-3xl gap-4 sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Vista previa del correo</DialogTitle>
                        <DialogDescription>
                            Asunto: {vistaPrevia?.asunto} · Para{" "}
                            {contrato.mail_contacto?.trim() || "—"}
                        </DialogDescription>
                    </DialogHeader>

                    {vistaPrevia && (
                        <iframe
                            title="Vista previa del correo"
                            sandbox=""
                            srcDoc={vistaPrevia.html}
                            className="h-[60vh] w-full rounded-md border"
                        />
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}

export function DocumentosContrato({ contrato, onCerrar }: DocumentosContratoProps) {
    return (
        <Dialog
            open={contrato !== null}
            onOpenChange={(abierto) => {
                if (!abierto) {
                    onCerrar();
                }
            }}
        >
            <DialogContent className="max-w-5xl gap-5 sm:max-w-5xl">
                {contrato && (
                    <>
                        <DialogHeader className="border-b pb-4">
                            <DialogTitle className="text-xl font-bold">
                                {contrato.familia ?? contrato.nombre}
                            </DialogTitle>
                            <DialogDescription>
                                Contrato {contrato.numero_base} · Se enviará a{" "}
                                {contrato.mail_contacto?.trim() || "—"}
                            </DialogDescription>
                        </DialogHeader>

                        <DetalleContrato
                            key={contrato.numero_base}
                            contrato={contrato}
                        />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
