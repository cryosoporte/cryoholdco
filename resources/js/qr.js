import qrcode from 'qrcode-generator';

/**
 * Dibuja un QR dentro de cada elemento con [data-qr], usando su valor como
 * contenido del código.
 *
 * El Blade ya imprime el texto dentro del contenedor, así que si la librería
 * falla queda el número de contrato legible y se puede dictar en recepción.
 *
 * @param {ParentNode} raiz  Dónde buscar. Se le pasa el contenedor del
 *                           resultado cuando el fragmento llega por fetch.
 */
export function pintarQr(raiz = document) {
    raiz.querySelectorAll('[data-qr]').forEach((caja) => {
        const texto = caja.dataset.qr;

        // qrListo evita redibujar si ya se pintó (p. ej. al volver a llamar).
        if (!texto || caja.dataset.qrListo === '1') return;

        try {
            const codigo = qrcode(0, 'M');
            codigo.addData(texto);
            codigo.make();

            // margin = cellSize * 4: la "zona silenciosa" que exige el estándar
            // para que los lectores lo reconozcan.
            caja.innerHTML = codigo.createSvgTag({ cellSize: 4, margin: 16, scalable: true });
            caja.querySelector('svg')?.classList.add('h-full', 'w-full');
            caja.dataset.qrListo = '1';
        } catch (fallo) {
            console.error('No se pudo generar el QR; queda el número en texto.', fallo);
        }
    });
}
