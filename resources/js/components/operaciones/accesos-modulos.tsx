import { Link } from "@inertiajs/react";
import { ArrowRightIcon, ExternalLinkIcon } from "lucide-react";
import { route } from "ziggy-js";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import { modulosOperaciones } from "@/components/operaciones/modulos";

export function AccesosModulos() {
    return (
        <div className="grid gap-4 md:grid-cols-3">
            {modulosOperaciones.map(
                ({ titulo, descripcion, ruta, icono: Icono, nuevaPestana }) => (
                    <Card
                        key={ruta}
                        className="border-t-4 border-t-primary transition-shadow hover:shadow-md"
                    >
                        <CardHeader>
                            <div className="mb-2 grid size-11 place-items-center rounded-xl bg-secondary text-secondary-foreground">
                                <Icono className="size-6" />
                            </div>
                            <CardTitle>
                                <h2>{titulo}</h2>
                            </CardTitle>
                            <CardDescription>{descripcion}</CardDescription>
                        </CardHeader>
                        <CardContent className="mt-auto">
                            {nuevaPestana ? (
                                <a
                                    href={route(ruta)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={buttonVariants({
                                        variant: "secondary",
                                    })}
                                >
                                    Abrir {titulo.toLowerCase()}
                                    <ExternalLinkIcon className="size-4" />
                                    <span className="sr-only">
                                        {" "}
                                        (nueva pestaña)
                                    </span>
                                </a>
                            ) : (
                                <Link
                                    href={route(ruta)}
                                    className={buttonVariants()}
                                >
                                    Abrir {titulo.toLowerCase()}
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            )}
                        </CardContent>
                    </Card>
                ),
            )}
        </div>
    );
}
