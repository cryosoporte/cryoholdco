import { Badge, badgeVariants } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import type { PersonaUsuario, RolDisponible } from "./tipos";

function AccionRolUsuario({
    persona,
    onSeleccionar,
}: {
    persona: PersonaUsuario;
    onSeleccionar: (persona: PersonaUsuario) => void;
}) {
    const sinRol = persona.roles.length === 0;
    const accion = sinRol ? "Asignar roles" : "Cambiar roles";
    const boton = (
        <Button
            type="button"
            variant={sinRol ? "default" : "outline"}
            aria-label={`${accion} de ${persona.nombre}`}
            disabled={persona.es_usted}
            focusableWhenDisabled={persona.es_usted}
            className={
                persona.es_usted
                    ? "cursor-not-allowed aria-disabled:opacity-50"
                    : "cursor-pointer"
            }
            onClick={persona.es_usted ? undefined : () => onSeleccionar(persona)}
        >
            {accion}
        </Button>
    );

    return persona.es_usted ? (
        <Tooltip>
            <TooltipTrigger render={boton} />
            <TooltipContent>No puedes cambiar tus propios roles.</TooltipContent>
        </Tooltip>
    ) : boton;
}

export function TablaUsuarios({
    personas,
    rolesDisponibles,
    onSeleccionar,
}: {
    personas: PersonaUsuario[];
    rolesDisponibles: RolDisponible[];
    onSeleccionar: (persona: PersonaUsuario) => void;
}) {
    const nombresPorRol = new Map(rolesDisponibles.map(({ codigo, nombre }) => [codigo, nombre]));

    return (
        <Table aria-label="Usuarios">
            <TableHeader>
                <TableRow>
                    <TableHead scope="col">Persona</TableHead>
                    <TableHead scope="col">DNI</TableHead>
                    <TableHead scope="col">Roles</TableHead>
                    <TableHead scope="col" className="text-right">
                        Acciones
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {personas.map((persona) => (
                    <TableRow key={persona.id}>
                        <TableCell>
                            <div className="mb-0.5 flex items-center gap-2">
                                <p className="font-semibold">
                                    {persona.nombre}
                                </p>
                                {persona.es_usted && (
                                    <Badge variant="outline">Tú</Badge>
                                )}
                            </div>
                            {persona.correos.map((correo) => (
                                <p
                                    key={correo}
                                    className="text-xs text-muted-foreground"
                                >
                                    {correo}
                                </p>
                            ))}
                            </TableCell>
                            <TableCell className="font-mono">
                                {persona.dni || "—"}
                            </TableCell>
                            <TableCell>
                                <div className="flex flex-wrap items-center gap-2">
                                    {persona.roles.length === 0 && <Badge variant="outline">Sin rol</Badge>}
                                    {persona.roles.map((rol) => (
                                        <Badge key={rol} variant={rol === "ADMINISTRADOR" ? "default" : "secondary"}>
                                            {nombresPorRol.get(rol) ?? rol}
                                        </Badge>
                                    ))}
                                    {persona.roles_incompletos && (
                                        <Tooltip>
                                            <TooltipTrigger
                                                type="button"
                                                className={badgeVariants({
                                                    variant: "outline",
                                                    className: "cursor-help",
                                                })}
                                            >
                                                Revisar
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                Sus cuentas tienen roles distintos. Revisa la selección antes de guardar.
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className="text-right">
                                <AccionRolUsuario
                                    persona={persona}
                                    onSeleccionar={onSeleccionar}
                                />
                            </TableCell>
                        </TableRow>
                ))}

                {personas.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={4} className="py-6 text-center">
                            Ninguna persona coincide con la búsqueda.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
