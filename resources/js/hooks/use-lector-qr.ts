import { useCallback, useEffect, useRef, useState } from "react";

type DetectorQr = {
    detect: (video: HTMLVideoElement) => Promise<{ rawValue: string }[]>;
};
type VentanaConDetector = Window & {
    BarcodeDetector?: new (opciones: { formats: string[] }) => DetectorQr;
};

export function useLectorQr(onLeer: (codigo: string) => void) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const temporizadorRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const versionRef = useRef(0);
    const onLeerRef = useRef(onLeer);
    const [disponible, setDisponible] = useState(false);
    const [abierta, setAbierta] = useState(false);
    const [abriendo, setAbriendo] = useState(false);
    const [mensaje, setMensaje] = useState<string | null>(null);

    useEffect(() => {
        onLeerRef.current = onLeer;
    }, [onLeer]);

    const detener = useCallback(() => {
        versionRef.current += 1;
        if (temporizadorRef.current !== null) {
            clearTimeout(temporizadorRef.current);
            temporizadorRef.current = null;
        }
        streamRef.current?.getTracks().forEach((pista) => pista.stop());
        streamRef.current = null;
        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
    }, []);

    const cerrar = useCallback(() => {
        detener();
        setAbierta(false);
        setAbriendo(false);
        setMensaje(null);
    }, [detener]);

    useEffect(() => {
        setDisponible(!!navigator.mediaDevices?.getUserMedia);
        function alOcultar(): void {
            if (document.hidden) {
                cerrar();
            }
        }
        document.addEventListener("visibilitychange", alOcultar);
        return () => {
            document.removeEventListener("visibilitychange", alOcultar);
            detener();
        };
    }, [cerrar, detener]);

    async function abrir(): Promise<void> {
        detener();
        const version = versionRef.current;
        setAbierta(true);
        setAbriendo(true);
        setMensaje("Abriendo cámara…");

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: "environment" } },
            });
            if (version !== versionRef.current) {
                stream.getTracks().forEach((pista) => pista.stop());
                return;
            }
            streamRef.current = stream;
            const video = videoRef.current;
            if (!video) {
                cerrar();
                return;
            }
            video.srcObject = stream;
            await video.play();
            if (version !== versionRef.current) {
                return;
            }
            setAbriendo(false);
            setMensaje("Apunta al código QR de la clienta.");

            let detector: DetectorQr | null = null;
            try {
                const Constructor = (window as VentanaConDetector)
                    .BarcodeDetector;
                detector = Constructor
                    ? new Constructor({ formats: ["qr_code"] })
                    : null;
            } catch {
                detector = null;
            }
            const lienzo = document.createElement("canvas");
            const contexto = lienzo.getContext("2d", {
                willReadFrequently: true,
            });
            let lectorAlternativo: typeof import("jsqr").default | null = null;

            async function leer(): Promise<void> {
                if (version !== versionRef.current) {
                    return;
                }
                let codigo: string | null = null;
                try {
                    if (detector) {
                        try {
                            codigo =
                                (await detector.detect(video!))[0]?.rawValue ??
                                null;
                        } catch {
                            detector = null;
                        }
                    }
                    if (
                        !codigo &&
                        contexto &&
                        video!.videoWidth > 0 &&
                        video!.videoHeight > 0
                    ) {
                        lectorAlternativo ??= (await import("jsqr")).default;
                        if (version !== versionRef.current) {
                            return;
                        }
                        const escala = Math.min(
                            1,
                            640 /
                                Math.max(video!.videoWidth, video!.videoHeight),
                        );
                        lienzo.width = Math.round(video!.videoWidth * escala);
                        lienzo.height = Math.round(video!.videoHeight * escala);
                        contexto.drawImage(
                            video!,
                            0,
                            0,
                            lienzo.width,
                            lienzo.height,
                        );
                        codigo =
                            lectorAlternativo(
                                contexto.getImageData(
                                    0,
                                    0,
                                    lienzo.width,
                                    lienzo.height,
                                ).data,
                                lienzo.width,
                                lienzo.height,
                                { inversionAttempts: "dontInvert" },
                            )?.data ?? null;
                    }
                } catch {
                    if (version === versionRef.current) {
                        detener();
                        setAbriendo(false);
                        setMensaje(
                            "No pudimos leer la cámara. Escribe el código o el DNI a mano.",
                        );
                    }
                    return;
                }
                if (version !== versionRef.current) {
                    return;
                }
                if (codigo?.trim()) {
                    cerrar();
                    onLeerRef.current(codigo.trim());
                    return;
                }
                temporizadorRef.current = setTimeout(() => {
                    void leer();
                }, 120);
            }

            void leer();
        } catch (error) {
            if (version !== versionRef.current) {
                return;
            }
            detener();
            setAbriendo(false);
            setMensaje(
                error instanceof DOMException &&
                    error.name === "NotAllowedError"
                    ? "No diste permiso para la cámara. Habilítala en el navegador o escribe el código a mano."
                    : "No pudimos abrir la cámara. Escribe el código o el DNI a mano.",
            );
        }
    }

    return { videoRef, disponible, abierta, abriendo, mensaje, abrir, cerrar };
}
