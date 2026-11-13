import { useForm } from "@inertiajs/react";
import { route } from "ziggy-js";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { FormError } from "@/components/form-error";
import { useErroresPeticion } from "@/hooks/use-errores-peticion";

/** Inicio de sesión del laboratorio con su RUC, basado en el bloque login-01 de shadcn. */
export function FormularioLogin() {
    const formulario = useForm({ ruc: "", password: "" });
    const { errorPeticion, opcionesPeticion } = useErroresPeticion();

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <h1>Ingresa al mostrador</h1>
                </CardTitle>
                <CardDescription>
                    Usa el RUC del laboratorio y la contraseña que te dio
                    Criocord.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(evento) => {
                        evento.preventDefault();
                        if (!formulario.processing) {
                            formulario.post(route("laboratorio.login.store"), {
                                ...opcionesPeticion,
                                onFinish: () => formulario.reset("password"),
                            });
                        }
                    }}
                    aria-busy={formulario.processing}
                >
                    <FieldGroup>
                        <Field data-invalid={!!formulario.errors.ruc}>
                            <FieldLabel htmlFor="ruc">RUC</FieldLabel>
                            <Input
                                id="ruc"
                                name="ruc"
                                inputMode="numeric"
                                autoComplete="username"
                                value={formulario.data.ruc}
                                onChange={(evento) =>
                                    formulario.setData(
                                        "ruc",
                                        evento.target.value.replace(/\D/g, ""),
                                    )
                                }
                                required
                                maxLength={15}
                                autoFocus
                                className="font-mono tracking-widest"
                                disabled={formulario.processing}
                                aria-invalid={!!formulario.errors.ruc}
                                aria-describedby={
                                    formulario.errors.ruc ? "error-ruc" : undefined
                                }
                            />
                            <FieldError id="error-ruc">
                                {formulario.errors.ruc}
                            </FieldError>
                        </Field>
                        <Field data-invalid={!!formulario.errors.password}>
                            <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                value={formulario.data.password}
                                onChange={(evento) =>
                                    formulario.setData(
                                        "password",
                                        evento.target.value,
                                    )
                                }
                                required
                                disabled={formulario.processing}
                                aria-invalid={!!formulario.errors.password}
                                aria-describedby={
                                    formulario.errors.password
                                        ? "error-password"
                                        : undefined
                                }
                            />
                            <FieldError id="error-password">
                                {formulario.errors.password}
                            </FieldError>
                        </Field>
                        <FormError message={errorPeticion} />
                        <Field>
                            <Button
                                type="submit"
                                className="cursor-pointer"
                                disabled={formulario.processing}
                            >
                                {formulario.processing && (
                                    <Spinner aria-hidden="true" />
                                )}
                                {formulario.processing
                                    ? "Ingresando…"
                                    : "Ingresar"}
                            </Button>
                            <FieldDescription className="text-center">
                                ¿No tienes acceso o olvidaste la contraseña?
                                Comunícate con Criocord.
                            </FieldDescription>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    );
}
