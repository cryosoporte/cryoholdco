import { Link } from "@inertiajs/react";
import { LogOutIcon } from "lucide-react";
import { route } from "ziggy-js";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";

/** El laboratorio que inició sesión en el mostrador y el botón para salir. */
export function SesionLaboratorio({
    ruc,
    nombre,
    disabled,
}: {
    ruc: string;
    nombre: string | null;
    disabled: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <Badge variant="secondary" title={`RUC ${ruc}`}>
                {nombre ?? `RUC ${ruc}`}
            </Badge>
            <Link
                href={route("laboratorio.logout")}
                method="post"
                as="button"
                disabled={disabled}
                className={buttonVariants({
                    variant: "outline",
                    className: "cursor-pointer",
                })}
            >
                <LogOutIcon className="size-4" />
                Salir
            </Link>
        </div>
    );
}
