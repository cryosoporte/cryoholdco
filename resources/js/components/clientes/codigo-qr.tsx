import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { generarQr } from "@/lib/qr";

export function CodigoQr({ numeroContrato }: { numeroContrato: string }) {
    const imagen = useMemo(() => generarQr(numeroContrato), [numeroContrato]);

    return (
        <Card className="text-center">
            <CardHeader>
                <CardTitle>Tu código</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="mx-auto aspect-square w-full max-w-56 rounded-lg bg-white p-2">
                    {imagen ? (
                        <img
                            src={imagen}
                            alt={`Código QR del contrato ${numeroContrato}`}
                            className="size-full"
                        />
                    ) : (
                        <p className="grid h-full place-items-center break-all font-mono text-sm text-black">
                            {numeroContrato}
                        </p>
                    )}
                </div>
                <p className="break-all font-mono font-semibold">
                    {numeroContrato}
                </p>
                <p className="text-xs text-muted-foreground">
                    Muéstralo en recepción junto con tu DNI.
                </p>
            </CardContent>
        </Card>
    );
}
