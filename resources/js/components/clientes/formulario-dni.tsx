import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { LoaderCircleIcon } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent } from "@/components/ui/card";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";

export function FormularioDni() {
    const formulario = useForm({ dni: "" });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    return (
        <section className="mx-auto max-w-xl space-y-6">
            <div className="text-center">
                <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Consulta tu prueba serológica
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Escribe tu DNI y te decimos en qué va tu prueba.
                </p>
            </div>
            <Card>
                <CardContent>
                    <form
                        onSubmit={(evento) => {
                            evento.preventDefault();
                            if (!formulario.processing) {
                                formulario.post(route("clientes.consultar"), {
                                    ...opcionesPeticion,
                                    replace: true,
                                });
                            }
                        }}
                        className="space-y-4"
                        aria-busy={formulario.processing}
                    >
                        <Label htmlFor="dni">Tu DNI</Label>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="dni"
                                name="dni"
                                value={formulario.data.dni}
                                onChange={(evento) =>
                                    formulario.setData(
                                        "dni",
                                        evento.target.value.replace(/\D/g, ""),
                                    )
                                }
                                maxLength={8}
                                inputMode="numeric"
                                autoComplete="off"
                                autoFocus
                                placeholder="8 dígitos"
                                className="h-11 font-mono text-lg tracking-widest"
                                aria-invalid={!!formulario.errors.dni}
                                aria-describedby={
                                    formulario.errors.dni
                                        ? "error-dni"
                                        : undefined
                                }
                                disabled={formulario.processing}
                            />
                            <Button
                                type="submit"
                                className="h-11"
                                disabled={formulario.processing}
                            >
                                {formulario.processing && (
                                    <LoaderCircleIcon className="animate-spin" />
                                )}
                                {formulario.processing
                                    ? "Consultando…"
                                    : "Consultar"}
                            </Button>
                        </div>
                        <FormError
                            id="error-dni"
                            message={formulario.errors.dni}
                        />
                        <FormError message={errorPeticion} />
                    </form>
                </CardContent>
            </Card>
            <p className="text-center text-xs text-muted-foreground">
                La prueba está incluida en tu contrato Criocord.
            </p>
        </section>
    );
}
