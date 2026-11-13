import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import type { LaboratorioAcceso } from "./tipos";

type TablaLaboratoriosProps = {
    laboratorios: LaboratorioAcceso[];
    onSeleccionar: (laboratorio: LaboratorioAcceso) => void;
};

function EstadoAcceso({ laboratorio }: { laboratorio: LaboratorioAcceso }) {
    if (laboratorio.acceso === null) {
        return <Badge variant="outline">Sin acceso</Badge>;
    }

    return laboratorio.acceso.estado === "A" ? (
        <Badge>Activo</Badge>
    ) : (
        <Badge variant="secondary">Inactivo</Badge>
    );
}

export function TablaLaboratorios({
    laboratorios,
    onSeleccionar,
}: TablaLaboratoriosProps) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Laboratorio</TableHead>
                    <TableHead>Sedes activas</TableHead>
                    <TableHead>Acceso</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {laboratorios.map((laboratorio) => {
                    const nombre = laboratorio.nombre ?? laboratorio.identificacion;

                    return (
                        <TableRow key={laboratorio.identificacion}>
                            <TableCell>
                                <p className="font-semibold">{nombre}</p>
                                <p className="font-mono text-xs text-muted-foreground">
                                    RUC {laboratorio.identificacion}
                                </p>
                            </TableCell>
                            <TableCell>
                                {laboratorio.sedes_activas > 0 ? (
                                    laboratorio.sedes_activas
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Sin sedes activas: no podrá ingresar
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                <EstadoAcceso laboratorio={laboratorio} />
                            </TableCell>
                            <TableCell className="text-right">
                                <Button
                                    type="button"
                                    variant={
                                        laboratorio.acceso === null
                                            ? "default"
                                            : "outline"
                                    }
                                    className="cursor-pointer"
                                    aria-label={`${
                                        laboratorio.acceso === null
                                            ? "Crear acceso"
                                            : "Editar acceso"
                                    } de ${nombre}`}
                                    onClick={() => onSeleccionar(laboratorio)}
                                >
                                    {laboratorio.acceso === null
                                        ? "Crear acceso"
                                        : "Editar"}
                                </Button>
                            </TableCell>
                        </TableRow>
                    );
                })}

                {laboratorios.length === 0 && (
                    <TableRow>
                        <TableCell colSpan={4} className="py-6 text-center">
                            No hay laboratorios con sedes activas.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
