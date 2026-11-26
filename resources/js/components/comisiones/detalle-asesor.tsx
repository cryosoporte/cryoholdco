import { route } from "ziggy-js";
import { Button } from "@/components/ui/button";
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { formatearSoles } from "./formato";
import type { Asesor } from "./tipos";

export function DetalleAsesor({ asesor, idLiquidacion, puedeVerCorreo, onCerrar }: {
    asesor: Asesor | null;
    idLiquidacion: string;
    puedeVerCorreo: boolean;
    onCerrar: () => void;
}) {
    return (
        <Sheet open={asesor !== null} onOpenChange={(abierto) => { if (!abierto) onCerrar(); }}>
            {asesor && (
                <SheetContent className="overflow-y-auto data-[side=right]:w-full data-[side=right]:sm:max-w-3xl" showCloseButton={false}>
                    <SheetHeader className="relative border-b pr-24">
                        <SheetTitle className="text-lg">{asesor.nombre}</SheetTitle>
                        <SheetDescription>{asesor.cargo}</SheetDescription>
                        <SheetClose render={<Button type="button" variant="outline" size="sm" className="absolute top-4 right-4" />}>Cerrar</SheetClose>
                    </SheetHeader>
                    <div className="min-w-0 space-y-4 px-4 pb-4">
                        <div className="overflow-hidden rounded-lg border">
                            <Table aria-label="Detalle de la comisión">
                                <TableHeader className="bg-muted/40">
                                    <TableRow>
                                        <TableHead scope="col">Concepto</TableHead>
                                        <TableHead scope="col">Logrado</TableHead>
                                        <TableHead scope="col">Cuota</TableHead>
                                        <TableHead scope="col">Tramo aplicado</TableHead>
                                        <TableHead scope="col" className="text-right">Comisión S/</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {asesor.lineas.map((linea) => (
                                        <TableRow key={linea.concepto}>
                                            <TableCell className="min-w-32 whitespace-normal font-medium">{linea.concepto}</TableCell>
                                            <TableCell className="tabular-nums">{linea.logrado}</TableCell>
                                            <TableCell className="tabular-nums">{linea.cuota}</TableCell>
                                            <TableCell>{linea.tramo}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatearSoles(linea.comision)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter><TableRow>
                                    <TableCell colSpan={4}>Total</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatearSoles(asesor.total)}</TableCell>
                                </TableRow></TableFooter>
                            </Table>
                        </div>
                        <p className="rounded-lg bg-muted/50 px-4 py-3 leading-relaxed text-muted-foreground">{asesor.nota}</p>
                        {puedeVerCorreo && (
                            <a className="font-medium text-primary underline underline-offset-4"
                                href={route("reportes.comisiones.liquidaciones.correo-asesor", { liquidacion: idLiquidacion, asesor: asesor.id })}
                                target="_blank" rel="noreferrer">
                                Ver correo
                            </a>
                        )}
                    </div>
                </SheetContent>
            )}
        </Sheet>
    );
}
