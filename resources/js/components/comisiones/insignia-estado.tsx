import { Badge } from "@/components/ui/badge";
import { estados } from "./configuracion";
import type { EstadoLiquidacion } from "./tipos";

export function InsigniaEstado({ estado }: { estado: EstadoLiquidacion }) {
    const { clase, etiqueta, ayuda } = estados[estado];

    return <Badge variant="outline" className={clase} title={ayuda}>{etiqueta}</Badge>;
}
