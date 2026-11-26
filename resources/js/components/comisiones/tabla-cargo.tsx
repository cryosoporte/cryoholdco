import { InfoIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { formatearSoles } from "./formato";
import type { Cargo } from "./tipos";

/**
 * La regla llega como "Atribución: X · UCU 15+ × S/ 50 · ADN 5+ × S/ 30":
 * el tooltip muestra solo las líneas con sus tramos, sin el campo de la
 * atribución.
 */
function ReglaCargo({ regla }: { regla: string }) {
    const lineas = regla.split(" · ").slice(1);

    return (
        <Tooltip>
            <TooltipTrigger className="inline-flex shrink-0 cursor-help items-center gap-1.5 rounded-md px-2 py-1 text-sm text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
                Atribución
                <InfoIcon aria-hidden="true" className="size-4" />
            </TooltipTrigger>
            <TooltipContent
                side="bottom"
                align="end"
                className="max-w-sm flex-col items-start gap-1 py-2 text-left"
            >
                <p className="font-semibold">Atribución</p>
                {lineas.length > 0 && (
                    <ul className="space-y-0.5">
                        {lineas.map((linea) => (
                            <li key={linea}>{linea}</li>
                        ))}
                    </ul>
                )}
            </TooltipContent>
        </Tooltip>
    );
}

export function TablaCargo({
    cargo,
    onSeleccionar,
}: {
    cargo: Cargo;
    onSeleccionar: (id: string) => void;
}) {
    return (
        <div className="min-w-0 overflow-hidden rounded-xl border bg-card">
            <div className="flex items-center justify-between gap-3 border-b px-4 py-3">
                <h3 className="text-base font-semibold">{cargo.titulo}</h3>
                <ReglaCargo regla={cargo.regla} />
            </div>
            <Table
                aria-label={cargo.titulo}
                className="[&_td]:px-4 [&_th]:px-4"
            >
                <TableHeader className="bg-muted/35">
                    <TableRow>
                        <TableHead scope="col">Colaborador</TableHead>
                        {cargo.columnas.map((columna) => (
                            <TableHead
                                key={columna}
                                scope="col"
                                className="text-right"
                            >
                                {columna}
                            </TableHead>
                        ))}
                        <TableHead scope="col" className="text-right">
                            Comisión S/
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {cargo.filas.map((fila) => (
                        <TableRow key={fila.asesor_id}>
                            <TableCell>
                                <Button
                                    type="button"
                                    variant="link"
                                    aria-haspopup="dialog"
                                    className="h-auto cursor-pointer justify-start p-0 text-left font-semibold"
                                    onClick={() =>
                                        onSeleccionar(fila.asesor_id)
                                    }
                                >
                                    {fila.nombre}
                                </Button>
                            </TableCell>
                            {fila.celdas.map((celda, indice) => (
                                <TableCell
                                    key={cargo.columnas[indice]}
                                    className={cn(
                                        "text-right tabular-nums",
                                        celda.alcanzo === true &&
                                            "font-semibold text-emerald-700 dark:text-emerald-300",
                                        celda.alcanzo === false &&
                                            "text-muted-foreground",
                                    )}
                                >
                                    {celda.texto}
                                    {celda.alcanzo !== null && (
                                        <span className="sr-only">
                                            {celda.alcanzo
                                                ? ", alcanzó la cuota"
                                                : ", no alcanzó la cuota"}
                                        </span>
                                    )}
                                </TableCell>
                            ))}
                            <TableCell className="text-right font-semibold tabular-nums">
                                {formatearSoles(fila.comision)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell colSpan={cargo.columnas.length + 1}>
                            Total del cargo
                        </TableCell>
                        <TableCell className="text-right font-semibold tabular-nums">
                            {formatearSoles(cargo.total)}
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
            <p className="border-t px-4 py-3 text-xs text-muted-foreground">
                Verde: alcanzó la cuota mínima. Gris: no comisiona en esa línea.
            </p>
        </div>
    );
}
