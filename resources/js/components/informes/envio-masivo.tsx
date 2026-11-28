import { AlertTriangleIcon } from "lucide-react";

import { Alert, AlertDescription } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

import type { ContratoSeleccionado } from "./tipos";
import { useEnvioMasivo } from "./use-envio-masivo";

type EnvioMasivoProps = {
    abierto: boolean;
    contratos: ContratoSeleccionado[];
    tamanoLote: number;
    onCerrar: (huboEnvios: boolean) => void;
};

export function EnvioMasivo({
    abierto,
    contratos,
    tamanoLote,
    onCerrar,
}: EnvioMasivoProps) {
    const { enviar, enviando, procesados, resultado, reiniciar } = useEnvioMasivo(
        contratos,
        tamanoLote,
    );
    const terminado = resultado !== null && !enviando;
    const nombres = new Map(
        contratos.map((contrato) => [contrato.numero_base, contrato.nombre]),
    );

    function cerrar(): void {
        const huboEnvios = (resultado?.enviados.length ?? 0) > 0;

        reiniciar();
        onCerrar(huboEnvios);
    }

    return (
        <Dialog
            open={abierto}
            onOpenChange={(estaAbierto) => {
                if (!estaAbierto && !enviando) {
                    cerrar();
                }
            }}
        >
            <DialogContent className="max-w-2xl gap-5">
                <DialogHeader className="border-b pb-4">
                    <DialogTitle className="text-xl font-bold">
                        Enviar documentos a {contratos.length}{" "}
                        {contratos.length === 1 ? "contrato" : "contratos"}
                    </DialogTitle>
                    <DialogDescription>
                        Cada cliente recibe un correo en su dirección de contacto.
                    </DialogDescription>
                </DialogHeader>

                {resultado === null && (
                    <>
                        <Alert>
                            <AlertTriangleIcon />
                            <AlertDescription>
                                Se enviarán solo los archivos <strong>predeterminados</strong> (RHE, RMB, RSC, MSC, REPORTE, RPU) de la
                                carpeta de cada contrato. Si necesitas elegir qué
                                archivos enviar, hazlo contrato por contrato desde
                                «Ver documentos». Los contratos con algún resultado NO APTO también se envían desde «Ver documentos», porque hay que elegir el motivo.
                                {contratos.some((contrato) => contrato.enviado) &&
                                    " Los marcados «Ya enviado» se enviarán de nuevo."}
                            </AlertDescription>
                        </Alert>

                        <ul className="max-h-64 divide-y overflow-y-auto rounded-md border text-sm">
                            {contratos.map((contrato) => (
                                <li
                                    key={contrato.numero_base}
                                    className="flex flex-wrap justify-between gap-2 px-3 py-2"
                                >
                                    <span className="font-medium">{contrato.familia ?? contrato.nombre}</span>
                                    <span className="text-muted-foreground">
                                        {contrato.enviado && "Ya enviado · "}
                                        {contrato.numero_base}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                className="cursor-pointer"
                                onClick={cerrar}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                className="cursor-pointer"
                                onClick={() => void enviar()}
                            >
                                Enviar a {contratos.length}{" "}
                                {contratos.length === 1 ? "cliente" : "clientes"}
                            </Button>
                        </div>
                    </>
                )}

                {enviando && (
                    <div className="space-y-2" role="status">
                        <p className="text-sm">
                            Enviando… {procesados} de {contratos.length} contratos
                            procesados. No cierres esta ventana.
                        </p>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-criocord-600 transition-all"
                                style={{
                                    width: `${(procesados / contratos.length) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                )}

                {terminado && (
                    <div className="space-y-4">
                        <p className="text-sm" role="status">
                            <strong>{resultado.enviados.length}</strong>{" "}
                            {resultado.enviados.length === 1 ? "envío" : "envíos"}{" "}
                            realizados
                            {resultado.omitidos.length > 0 && (
                                <>
                                    {" y "}
                                    <strong>{resultado.omitidos.length}</strong>{" "}
                                    omitidos
                                </>
                            )}
                            .
                        </p>

                        {resultado.omitidos.length > 0 && (
                            <ul className="max-h-64 divide-y overflow-y-auto rounded-md border text-sm">
                                {resultado.omitidos.map((omitido, indice) => (
                                    <li
                                        key={`${omitido.numero_base}-${indice}`}
                                        className="space-y-0.5 px-3 py-2"
                                    >
                                        <p className="font-medium">
                                            {nombres.get(omitido.numero_base) ??
                                                omitido.numero_base}{" "}
                                            <span className="font-normal text-muted-foreground">
                                                ({omitido.numero_base})
                                            </span>
                                        </p>
                                        <p className="text-destructive">{omitido.motivo}</p>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                className="cursor-pointer"
                                onClick={cerrar}
                            >
                                Cerrar
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
