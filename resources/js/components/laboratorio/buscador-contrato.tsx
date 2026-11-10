import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { LoaderCircleIcon, SearchIcon } from "lucide-react";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";
import { LectorQr } from "./lector-qr";

export function BuscadorContrato({
    disabled,
    errorCodigo,
    onProcesando,
    onResultado,
}: {
    disabled: boolean;
    errorCodigo?: string;
    onProcesando: (valor: boolean) => void;
    onResultado: () => void;
}) {
    const formulario = useForm({ codigo: "" });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();
    const ocupado = disabled || formulario.processing;
    const error = errorCodigo;

    function consultar(codigo: string): void {
        if (ocupado) {
            return;
        }
        formulario.transform(() => ({ codigo }));
        formulario.post(route("laboratorio.consultar"), {
            ...opcionesPeticion,
            replace: true,
            preserveScroll: true,
            onStart: (visita) => {
                opcionesPeticion.onStart?.(visita);
                onProcesando(true);
            },
            onSuccess: onResultado,
            onFinish: () => onProcesando(false),
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <h1 className="text-xl font-semibold">Clienta Criocord</h1>
                </CardTitle>
                <CardDescription>
                    Escanea su QR o escribe el código del contrato o su DNI.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <LectorQr
                    disabled={ocupado}
                    onLeer={(codigo) => {
                        formulario.setData("codigo", codigo);
                        consultar(codigo);
                    }}
                />
                <Separator />
                <form
                    className="space-y-3"
                    aria-busy={formulario.processing}
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        consultar(formulario.data.codigo);
                    }}
                >
                    <Label htmlFor="codigo">Código o DNI</Label>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <Input
                            id="codigo"
                            name="codigo"
                            value={formulario.data.codigo}
                            onChange={(evento) =>
                                formulario.setData(
                                    "codigo",
                                    evento.target.value,
                                )
                            }
                            maxLength={40}
                            autoComplete="off"
                            autoCapitalize="characters"
                            spellCheck={false}
                            autoFocus
                            placeholder="PE-CR-2026-000000-0 o DNI"
                            className="h-11 font-mono uppercase placeholder:normal-case"
                            disabled={ocupado}
                            aria-invalid={!!error}
                            aria-describedby={
                                error ? "error-codigo" : undefined
                            }
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-11"
                            disabled={ocupado}
                        >
                            {formulario.processing ? (
                                <LoaderCircleIcon className="animate-spin" />
                            ) : (
                                <SearchIcon />
                            )}
                            Buscar
                        </Button>
                    </div>
                    <FormError id="error-codigo" message={error} />
                    <FormError message={errorPeticion} />
                </form>
                <p className="text-xs text-muted-foreground">
                    Con un lector QR de mostrador (USB) el código se escribe
                    solo en este campo. Si la clienta no trae el celular, digita
                    su DNI.
                </p>
            </CardContent>
        </Card>
    );
}
