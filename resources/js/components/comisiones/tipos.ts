import type { ActualizacionFuente } from "@/components/reportes/tipos";

export type EstadoLiquidacion =
    | "borrador"
    | "enviada"
    | "rechazada"
    | "aprobada"
    | "enviada_personal";

export type CodigoCargo = "EJEC" | "LIMA" | "PROV" | "ANUAL" | "OPE" | "MKT";

/** Fila del historial. */
export type ResumenLiquidacion = {
    id: string;
    periodo: string;
    tipo_periodo: "mensual" | "trimestral";
    desde: string;
    hasta: string;
    estado: EstadoLiquidacion;
    total: number | null;
    colaboradores_con_comision: number | null;
    colaboradores: number | null;
    actualizada_en: string | null;
};

export type BaseLiquidacion = {
    actualizacion_fuente?: ActualizacionFuente | null;
    fuente: string;
    archivo: string;
    filas: number;
    desde: string;
    hasta: string;
    servicios_sin_configurar: number;
    nombres_sin_configurar: number;
};

export type Indicador = {
    etiqueta: string;
    valor: string;
    detalle: string | null;
    tono: "neutral" | "positivo" | "atencion";
};

export type CeldaCargo = {
    texto: string;
    alcanzo: boolean | null;
};

export type FilaCargo = {
    asesor_id: string;
    nombre: string;
    celdas: CeldaCargo[];
    comision: number;
};

export type Cargo = {
    codigo: CodigoCargo;
    etiqueta: string;
    titulo: string;
    regla: string;
    columnas: string[];
    filas: FilaCargo[];
    total: number;
};

export type LineaAsesor = {
    concepto: string;
    logrado: string;
    cuota: string;
    tramo: string;
    comision: number;
};

export type Asesor = {
    id: string;
    nombre: string;
    nombre_corto: string;
    correo: string;
    cargo_codigo: CodigoCargo;
    cargo: string;
    total: number;
    lineas: LineaAsesor[];
    nota: string;
};

export type EnvioAsesor = {
    asesor_id: string;
    nombre: string;
    correo: string;
    estado: "pendiente" | "enviado" | "fallido";
    enviado_en: string | null;
    error: string | null;
};

export type EventoLiquidacion = {
    tipo: "generada" | "procesada" | "rechazada" | "aprobada" | "enviada_personal" | "reenvio";
    fecha: string;
    por: string;
    comentario: string | null;
};

export type ResumenCargo = {
    cargo: string;
    con_comision: number;
    colaboradores: number;
    total: number;
};

export type Liquidacion = ResumenLiquidacion & {
    liquidacion_id: number | null;
    total: number;
    colaboradores: number;
    colaboradores_con_comision: number;
    actualizada_en: string;
    base: BaseLiquidacion;
    indicadores: Indicador[];
    cargos: Cargo[];
    resumen_cargos: ResumenCargo[];
    puntos_revision: string[];
    asesores: Asesor[];
    envios: EnvioAsesor[];
    historial: EventoLiquidacion[];
    correo_gerencia: string;
};

export type PermisosLiquidacion = {
    puede_procesar: boolean;
    puede_aprobar: boolean;
    puede_rechazar: boolean;
    puede_enviar: boolean;
    puede_reenviar: boolean;
};
