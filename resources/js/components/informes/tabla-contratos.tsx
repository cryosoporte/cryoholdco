import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

import { motivoNoSeleccionable, puedeSeleccionarse } from "./configuracion";
import type { ContratoInforme } from "./tipos";

type TablaContratosProps = {
    contratos: ContratoInforme[];
    actualizando: boolean;
    estaMarcado: (contrato: ContratoInforme) => boolean;
    onAlternar: (contrato: ContratoInforme, marcado: boolean) => void;
    onAlternarPagina: (contratos: ContratoInforme[], marcado: boolean) => void;
    onVerDocumentos: (contrato: ContratoInforme) => void;
};

const formatoFecha = new Intl.DateTimeFormat("es-PE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    timeZone: "UTC",
});

function formatearFecha(fecha: string | null): string {
    if (fecha === null) {
        return "";
    }

    const dia = new Date(`${fecha.slice(0, 10)}T00:00:00Z`);

    return Number.isNaN(dia.getTime()) ? "" : formatoFecha.format(dia);
}

function InsigniaLiberacion({ valor }: { valor: string | null }) {
    if (valor === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    if (valor === "APTO") {
        return <Badge variant="secondary">APTO</Badge>;
    }

    return (
        <Badge variant={valor === "NO APTO" ? "destructive" : "outline"}>
            {valor}
        </Badge>
    );
}

export function TablaContratos({
    contratos,
    actualizando,
    estaMarcado,
    onAlternar,
    onAlternarPagina,
    onVerDocumentos,
}: TablaContratosProps) {
    const seleccionables = contratos.filter(puedeSeleccionarse);
    const marcadosEnPagina = seleccionables.filter(estaMarcado).length;
    const todosMarcados =
        seleccionables.length > 0 && marcadosEnPagina === seleccionables.length;

    return (
        <Table aria-busy={actualizando}>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-10">
                        <Checkbox
                            aria-label="Marcar todos los contratos de esta página"
                            checked={todosMarcados}
                            indeterminate={
                                marcadosEnPagina > 0 && !todosMarcados
                            }
                            disabled={actualizando || seleccionables.length === 0}
                            onCheckedChange={(marcado) =>
                                onAlternarPagina(seleccionables, marcado)
                            }
                        />
                    </TableHead>
                    <TableHead>Contrato base</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Sangre</TableHead>
                    <TableHead>Tejido</TableHead>
                    <TableHead>Familia</TableHead>
                    <TableHead>Fecha colecta</TableHead>
                    <TableHead>Fecha envío</TableHead>
                    <TableHead>Usuario envío</TableHead>
                    <TableHead className="text-right">Documentos</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {contratos.map((contrato) => (
                    <TableRow
                        key={contrato.numero_base}
                        data-state={
                            estaMarcado(contrato) ? "selected" : undefined
                        }
                    >
                        <TableCell>
                            <Checkbox
                                aria-label={`Marcar el contrato ${contrato.numero_base}`}
                                title={motivoNoSeleccionable(contrato) ?? undefined}
                                checked={estaMarcado(contrato)}
                                disabled={
                                    actualizando || !puedeSeleccionarse(contrato)
                                }
                                onCheckedChange={(marcado) =>
                                    onAlternar(contrato, marcado)
                                }
                            />
                        </TableCell>
                        <TableCell className="font-medium">
                            {contrato.numero_base}
                        </TableCell>
                        <TableCell>{contrato.estado ?? "—"}</TableCell>
                        <TableCell>
                            <InsigniaLiberacion valor={contrato.sangre} />
                        </TableCell>
                        <TableCell>
                            <InsigniaLiberacion valor={contrato.tejido} />
                        </TableCell>
                        <TableCell>{contrato.familia ?? "—"}</TableCell>
                        <TableCell>
                            {formatearFecha(contrato.fecha_colecta)}
                        </TableCell>
                        <TableCell>{formatearFecha(contrato.fecha_envio)}</TableCell>
                        <TableCell>{contrato.usuario_envio ?? ""}</TableCell>
                        <TableCell className="text-right">
                            <Button
                                variant="outline"
                                type="button"
                                className="cursor-pointer"
                                title={
                                    contrato.tiene_carpeta === false
                                        ? "No hay carpeta de documentos para este contrato."
                                        : undefined
                                }
                                disabled={
                                    actualizando ||
                                    contrato.tiene_carpeta === false
                                }
                                onClick={() => onVerDocumentos(contrato)}
                            >
                                {contrato.tiene_carpeta === false
                                    ? "Sin carpeta"
                                    : "Ver documentos"}
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}

                {contratos.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={10} className="py-6 text-center">
                            No se encontraron contratos.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
