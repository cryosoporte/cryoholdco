import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "cn";

import type { FiltrosTarjetas, ResumenInformes } from "./tipos";

type TarjetasResumenProps = {
    resumen: ResumenInformes | undefined;
    filtros: FiltrosTarjetas;
    deshabilitado: boolean;
    onAlternar: (clave: keyof FiltrosTarjetas, valor: string) => void;
    onLimpiar: () => void;
};

type Tarjeta = {
    clave: keyof FiltrosTarjetas;
    valor: string;
    etiqueta: string;
    cantidad: number | undefined;
};

function TarjetaFiltro({
    tarjeta,
    activa,
    deshabilitado,
    onAlternar,
}: {
    tarjeta: Tarjeta;
    activa: boolean;
    deshabilitado: boolean;
    onAlternar: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={activa}
            disabled={deshabilitado}
            onClick={onAlternar}
            className={cn(
                "min-w-28 cursor-pointer rounded-lg border bg-card px-3 py-2 text-left transition-colors hover:bg-muted/60 disabled:cursor-not-allowed disabled:opacity-60",
                activa && "border-criocord-600 bg-criocord-600/10 ring-1 ring-criocord-600",
            )}
        >
            {tarjeta.cantidad === undefined ? (
                <Skeleton className="h-7 w-14" />
            ) : (
                <p className="text-2xl font-semibold tabular-nums">
                    {tarjeta.cantidad.toLocaleString("es-PE")}
                </p>
            )}
            <p className="text-xs text-muted-foreground">{tarjeta.etiqueta}</p>
        </button>
    );
}

function Grupo({
    titulo,
    tarjetas,
    filtros,
    deshabilitado,
    onAlternar,
}: {
    titulo: string;
    tarjetas: Tarjeta[];
    filtros: FiltrosTarjetas;
    deshabilitado: boolean;
    onAlternar: (clave: keyof FiltrosTarjetas, valor: string) => void;
}) {
    return (
        <section className="space-y-2" aria-label={titulo}>
            <h2 className="text-xs font-semibold uppercase text-muted-foreground">
                {titulo}
            </h2>
            <div className="flex flex-wrap gap-2">
                {tarjetas.map((tarjeta) => (
                    <TarjetaFiltro
                        key={`${tarjeta.clave}-${tarjeta.valor}`}
                        tarjeta={tarjeta}
                        activa={filtros[tarjeta.clave] === tarjeta.valor}
                        deshabilitado={deshabilitado}
                        onAlternar={() =>
                            onAlternar(tarjeta.clave, tarjeta.valor)
                        }
                    />
                ))}
            </div>
        </section>
    );
}

export function TarjetasResumen({
    resumen,
    filtros,
    deshabilitado,
    onAlternar,
    onLimpiar,
}: TarjetasResumenProps) {
    const hayFiltroActivo = Object.values(filtros).some(
        (valor) => valor !== null,
    );

    const resultados = (servicio: "sangre" | "tejido", nombre: string): Tarjeta[] =>
        (
            [
                ["apto", "apto"],
                ["no_apto", "no apto"],
                ["no_liberado", "no liberado"],
            ] as const
        ).map(([valor, etiqueta]) => ({
            clave: servicio,
            valor,
            etiqueta: `${nombre} ${etiqueta}`,
            cantidad: resumen?.[servicio][valor],
        }));

    const grupos: { titulo: string; tarjetas: Tarjeta[] }[] = [
        {
            titulo: "Liberación",
            tarjetas: [
                {
                    clave: "liberacion",
                    valor: "liberado",
                    etiqueta: "Liberados",
                    cantidad: resumen?.liberacion.liberado,
                },
                {
                    clave: "liberacion",
                    valor: "no_liberado",
                    etiqueta: "No liberados",
                    cantidad: resumen?.liberacion.no_liberado,
                },
            ],
        },
        {
            titulo: "No enviados: días desde la colecta",
            tarjetas: [
                ["0_30", "0 - 30 días"],
                ["31_60", "31 - 60 días"],
                ["61_90", "61 - 90 días"],
                ["91_mas", "91 días a más"],
            ].map(([valor, etiqueta]) => ({
                clave: "antiguedad" as const,
                valor,
                etiqueta,
                cantidad: resumen?.antiguedad[valor],
            })),
        },
        {
            titulo: "Sangre",
            tarjetas: resultados("sangre", "Sangre"),
        },
        {
            titulo: "Tejido",
            tarjetas: resultados("tejido", "Tejido"),
        },
    ];

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap gap-x-8 gap-y-4">
                {grupos.map((grupo) => (
                    <Grupo
                        key={grupo.titulo}
                        titulo={grupo.titulo}
                        tarjetas={grupo.tarjetas}
                        filtros={filtros}
                        deshabilitado={deshabilitado}
                        onAlternar={onAlternar}
                    />
                ))}
            </div>

            {hayFiltroActivo && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="cursor-pointer"
                    onClick={onLimpiar}
                >
                    Quitar filtros de las tarjetas
                </Button>
            )}
        </div>
    );
}
