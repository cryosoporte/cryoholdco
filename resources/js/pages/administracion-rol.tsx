import { Link } from "@inertiajs/react";
import { ArrowLeftIcon } from "lucide-react";
import { route } from "ziggy-js";
import { PermisosRol } from "@/components/administracion/permisos-rol";
import type { ModuloPermisos, PersonaConRol, RolDetalle } from "@/components/administracion/tipos";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import AppLayout from "@/layouts/app-layout";

export default function AdministracionRol({
    rol,
    catalogo,
    personas,
}: {
    rol: RolDetalle;
    catalogo: ModuloPermisos[];
    personas: PersonaConRol[];
}) {
    return (
        <AppLayout title={`Administración - ${rol.nombre}`}>
            <div className="space-y-6">
                <div className="space-y-2">
                    <Link href={route("administracion.roles")} className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
                        <ArrowLeftIcon className="size-4" aria-hidden="true" />
                        Volver a Roles
                    </Link>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold">{rol.nombre}</h1>
                        {rol.es_sistema && <Badge variant="secondary">Sistema</Badge>}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Código <code className="font-mono">{rol.codigo}</code>
                    </p>
                    {rol.descripcion && <p className="whitespace-pre-wrap wrap-break-word text-sm">{rol.descripcion}</p>}
                </div>

                <PermisosRol key={rol.codigo} rol={rol} catalogo={catalogo} />

                {rol.obsoletos.length > 0 && (
                    <Alert>
                        <AlertTitle>Permisos obsoletos</AlertTitle>
                        <AlertDescription>
                            <p>Estos códigos se conservan como referencia y no conceden acceso.</p>
                            <ul className="mt-1 space-y-1 font-mono">
                                {rol.obsoletos.map((codigo) => <li key={codigo}>{codigo}</li>)}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <section className="space-y-2" aria-labelledby="personas-con-rol">
                    <h2 id="personas-con-rol" className="font-semibold">Personas asignadas ({rol.personas})</h2>
                    {personas.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Este rol aún no tiene personas asignadas.</p>
                    ) : (
                        <ul className="divide-y rounded-lg border px-4 text-sm">
                            {personas.map(({ nombre, correos }, indice) => (
                                <li key={`${nombre}-${correos.join(",")}-${indice}`} className="space-y-1 py-2.5">
                                    <p className="font-medium">{nombre}</p>
                                    {correos.map((correo) => <p key={correo} className="wrap-break-word text-muted-foreground">{correo}</p>)}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
