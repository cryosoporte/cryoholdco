import { ScanLineIcon, XIcon } from "lucide-react";
import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { useLectorQr } from "@/components/laboratorio/use-lector-qr";

export function LectorQr({
    disabled,
    onLeer,
}: {
    disabled: boolean;
    onLeer: (codigo: string) => void;
}) {
    const camara = useLectorQr(onLeer);
    const cerrar = camara.cerrar;

    useEffect(() => {
        if (disabled) {
            cerrar();
        }
    }, [disabled, cerrar]);

    return (
        <div className="space-y-3">
            {camara.disponible && !camara.abierta && (
                <Button
                    type="button"
                    className="h-12 w-full"
                    disabled={disabled}
                    onClick={() => {
                        void camara.abrir();
                    }}
                >
                    <ScanLineIcon />
                    Escanear QR con la cámara
                </Button>
            )}
            <div hidden={!camara.abierta} className="space-y-3">
                <div className="relative overflow-hidden rounded-lg bg-black">
                    <video
                        ref={camara.videoRef}
                        playsInline
                        muted
                        className="max-h-80 w-full object-cover"
                        aria-label="Vista de la cámara para leer el código QR"
                    />
                    <div className="pointer-events-none absolute inset-6 rounded-lg border-2 border-white/70" />
                </div>
                <p
                    role="status"
                    className="text-center text-sm text-muted-foreground"
                >
                    {camara.mensaje}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    className="w-full"
                    onClick={camara.cerrar}
                >
                    <XIcon />
                    {camara.abriendo ? "Cancelar apertura" : "Cerrar cámara"}
                </Button>
            </div>
        </div>
    );
}
