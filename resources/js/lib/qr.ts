import qrcode from "qrcode-generator";

export function generarQr(texto: string): string | null {
    try {
        const codigo = qrcode(0, "M");
        codigo.addData(texto);
        codigo.make();
        return codigo.createDataURL(6, 24);
    } catch {
        return null;
    }
}
