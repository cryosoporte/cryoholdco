export type ContratoInforme = {
    numero_base: string;
    estado: string | null;
    sangre: string | null;
    tejido: string | null;
    familia: string | null;
    fecha_colecta: string | null;
    enviado: boolean;
    fecha_envio: string | null;
    usuario_envio: string | null;
    identificacion: string;
    nombre: string;
    mail_contacto: string | null;
    tiene_carpeta: boolean | null;
};

export type ContratosPaginados = {
    data: ContratoInforme[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type ArchivoDocumento = {
    nombre: string;
    tamano: number;
};

export type Titular = {
    titular: number;
    nombre: string;
    celular: string | null;
    correo: string | null;
};

export type ContratoSeleccionado = Pick<
    ContratoInforme,
    "numero_base" | "familia" | "nombre" | "identificacion" | "enviado"
>;

export type ResultadoEnvioMasivo = {
    enviados: string[];
    omitidos: { numero_base: string; motivo: string }[];
};

export type MotivoNoApto = {
    valor: string;
    etiqueta: string;
};

export type MotivosElegidos = {
    sangre?: string;
    tejido?: string;
};

export type VistaPreviaCorreo = {
    asunto: string;
    html: string;
};

export type FiltrosTarjetas = {
    envio: string | null;
    antiguedad: string | null;
    sangre: string | null;
    tejido: string | null;
    liberacion: string | null;
};

type ConteoResultados = { apto: number; no_apto: number; no_liberado: number };

export type ResumenInformes = {
    total: number;
    liberacion: { liberado: number; no_liberado: number };
    antiguedad: Record<string, number>;
    sangre: ConteoResultados;
    tejido: ConteoResultados;
};
