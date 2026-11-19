const soles = new Intl.NumberFormat("es-PE", {
    style: "currency",
    currency: "PEN",
});

export function formatearSoles(monto: number): string {
    return soles.format(monto);
}
