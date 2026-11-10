import { Link } from "@inertiajs/react";
import { route } from "ziggy-js";
import { LogOutIcon } from "lucide-react";
import PublicLayout from "@/layouts/public-layout";
import { Badge } from "@/components/ui/badge";
import { buttonVariants } from "@/components/ui/button";
import { FormularioDni } from "@/components/clientes/formulario-dni";
import { FichaCliente } from "@/components/clientes/ficha-cliente";
import type { Cliente } from "@/components/clientes/tipos";

export default function Clientes({ cliente }: { cliente: Cliente | null }) {
    return (
        <PublicLayout
            title="Consulta tu prueba serológica"
            actions={
                cliente ? (
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className="font-mono">
                            DNI {cliente.dni}
                        </Badge>
                        <Link
                            href={route("clientes.index")}
                            replace
                            preserveState={false}
                            className={buttonVariants({ variant: "outline" })}
                        >
                            <LogOutIcon className="size-4" />
                            Salir
                        </Link>
                    </div>
                ) : undefined
            }
        >
            {cliente ? <FichaCliente cliente={cliente} /> : <FormularioDni />}
        </PublicLayout>
    );
}
