import { Link } from "@inertiajs/react";
import { route } from "ziggy-js";
import { Badge } from "@/components/ui/badge";
import { Button, buttonVariants } from "@/components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type { RolResumen } from "./tipos";

export function TablaRoles({
    roles,
    onEditar,
    onEliminar,
}: {
    roles: RolResumen[];
    onEditar: (rol: RolResumen) => void;
    onEliminar: (rol: RolResumen) => void;
}) {
    return (
        <Table aria-label="Roles">
            <TableHeader>
                <TableRow>
                    <TableHead scope="col">Rol</TableHead>
                    <TableHead scope="col">Descripción</TableHead>
                    <TableHead scope="col" className="text-right">Personas</TableHead>
                    <TableHead scope="col" className="text-right">Permisos</TableHead>
                    <TableHead scope="col" className="text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {roles.map((rol) => (
                    <TableRow key={rol.codigo}>
                        <TableCell>
                            <div className="flex flex-wrap items-center gap-2 whitespace-normal">
                                <span className="font-semibold">{rol.nombre}</span>
                                {rol.es_sistema && <Badge variant="secondary">Sistema</Badge>}
                            </div>
                            <span className="font-mono text-xs text-muted-foreground">{rol.codigo}</span>
                        </TableCell>
                        <TableCell className="min-w-36 max-w-sm whitespace-normal wrap-break-word text-muted-foreground">
                            {rol.descripcion ?? "—"}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{rol.personas}</TableCell>
                        <TableCell className="text-right tabular-nums">{rol.permisos}</TableCell>
                        <TableCell>
                            <div className="flex justify-end gap-1.5">
                                <Link
                                    href={route("administracion.roles.mostrar", rol.codigo)}
                                    className={buttonVariants({ variant: "outline", size: "sm" })}
                                    aria-label={`Permisos de ${rol.nombre}`}
                                >
                                    Permisos
                                </Link>
                                <Button type="button" variant="outline" size="sm" aria-label={`Editar ${rol.nombre}`} onClick={() => onEditar(rol)}>
                                    Editar
                                </Button>
                                <BotonEliminarRol rol={rol} onEliminar={onEliminar} />
                            </div>
                        </TableCell>
                    </TableRow>
                ))}
                {roles.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={5} className="py-6 text-center">No hay roles registrados.</TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function BotonEliminarRol({
    rol,
    onEliminar,
}: {
    rol: RolResumen;
    onEliminar: (rol: RolResumen) => void;
}) {
    const bloqueado = rol.motivo_no_eliminar !== null;
    const boton = (
        <Button
            type="button"
            variant="destructive"
            size="sm"
            aria-label={`Eliminar ${rol.nombre}`}
            disabled={bloqueado}
            focusableWhenDisabled={bloqueado}
            className={bloqueado ? "cursor-not-allowed aria-disabled:opacity-50" : "cursor-pointer"}
            onClick={bloqueado ? undefined : () => onEliminar(rol)}
        >
            Eliminar
        </Button>
    );

    return bloqueado ? (
        <Tooltip>
            <TooltipTrigger render={boton} />
            <TooltipContent>{rol.motivo_no_eliminar}</TooltipContent>
        </Tooltip>
    ) : boton;
}
