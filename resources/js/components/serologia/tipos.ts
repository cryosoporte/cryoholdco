export type Plazo = {
    situacion:
        | "sin_fecha"
        | "aun_no_abre"
        | "en_plazo"
        | "por_vencer"
        | "fuera_de_plazo"
        | "tomada"
        | "recibido";
    fecha_apertura: string | null;
    fecha_limite: string | null;
    dias_restantes: number | null;
    porcentaje_transcurrido: number | null;
    texto: string;
};

export type Resultado = {
    no_cia: string;
    numero_base: string | null;
    identificacion: string;
    nombre: string;
    serologia: "S" | null;
    numero_toma: number | null;
    fecha_toma: string | null;
    fecha_resultado: string | null;
    asesor_dni: string | null;
    asesor_nombre: string | null;
    fecha_nacimiento: string | null;
    plazo: Plazo | null;
    detalle: DatosDetalleCliente;
};

export type DatosDetalleCliente = {
    celular: string | null;
    fpp: string | null;
    parto_real: string | null;
    sede_toma: string | null;
    plazo: Plazo;
};

export type Asesor = {
    dni: string;
    nombre: string | null;
};

export type ResultadosPaginados = {
    data: Resultado[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type EstadoSerologia =
    | "por_vencer"
    | "pendiente"
    | "tomada"
    | "recibido";
