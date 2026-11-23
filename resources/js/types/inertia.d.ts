import "@inertiajs/core";

/** Usuario con sesión iniciada, tal como lo comparte HandleInertiaRequests. */
export type UsuarioCompartido = {
    name: string;
    email: string;
};

/**
 * Props que HandleInertiaRequests comparte con todas las pantallas.
 * En las pantallas públicas (Clientes y Laboratorio) llegan en null.
 */
declare module "@inertiajs/core" {
    export interface InertiaConfig {
        sharedPageProps: {
            /** Base de URL para los archivos de public/. */
            assetUrl: string;
            empresa: string | null;
            user: UsuarioCompartido | null;
            roles: string[];
            rutasAdministracion: string[];
            rutasReportes: string[];
        };
        flashDataType: {
            fuenteCargada?: { fuente: string; filas: number };
            cargaFuenteRecibida?: string;
            comisiones?: { tipo: "exito" | "aviso"; titulo: string; descripcion: string };
        };
    }
}
