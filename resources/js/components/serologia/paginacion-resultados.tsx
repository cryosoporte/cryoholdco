import { Button } from "@/components/ui/button";
import type { ResultadosPaginados } from "./tipos";

type PaginacionResultadosProps = {
    paginacion: Pick<
        ResultadosPaginados,
        | "total"
        | "current_page"
        | "last_page"
        | "prev_page_url"
        | "next_page_url"
    >;
    actualizando: boolean;
    onCambiarPagina: (url: string) => void;
};

export function PaginacionResultados({
    paginacion,
    actualizando,
    onCambiarPagina,
}: PaginacionResultadosProps) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                {paginacion.total} resultados · Página {paginacion.current_page}
                {" de "}
                {paginacion.last_page}
            </p>

            <div className="flex gap-2">
                <Button
                    variant="outline"
                    disabled={actualizando || !paginacion.prev_page_url}
                    className="cursor-pointer"
                    onClick={() => {
                        if (paginacion.prev_page_url) {
                            onCambiarPagina(paginacion.prev_page_url);
                        }
                    }}
                >
                    Anterior
                </Button>

                <Button
                    variant="outline"
                    disabled={actualizando || !paginacion.next_page_url}
                    className="cursor-pointer"
                    onClick={() => {
                        if (paginacion.next_page_url) {
                            onCambiarPagina(paginacion.next_page_url);
                        }
                    }}
                >
                    Siguiente
                </Button>
            </div>
        </div>
    );
}
