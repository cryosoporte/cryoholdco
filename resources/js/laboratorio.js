/**
 * Mostrador del laboratorio: escanea el QR y marca la muestra como tomada.
 *
 * El servidor devuelve dos pedazos de HTML ya pintados —el semáforo y la
 * lista del día— y aquí solo se meten en su sitio. Sin este archivo el
 * formulario de búsqueda y el botón "Muestra tomada" siguen funcionando como
 * POST normales; lo único que se pierde es la cámara.
 */
import jsQR from 'jsqr';

const $ = (sel) => document.querySelector(sel);

const formCodigo = $('[data-form-codigo]');
const formSede = $('[data-form-sede]');
const campo = $('#codigo');
const error = $('[data-error-codigo]');
const semaforo = $('[data-semaforo]');
const panelHoy = $('[data-hoy]');

const botonAbrir = $('[data-abrir-camara]');
const botonCerrar = $('[data-cerrar-camara]');
const caja = $('[data-camara]');
const video = $('[data-video]');
const estadoCamara = $('[data-camara-estado]');

const token = () => document.querySelector('input[name="_token"]')?.value ?? '';

/** Pinta lo que devuelve el servidor. */
function pintar(datos) {
    if (datos.semaforo !== undefined) semaforo.innerHTML = datos.semaforo;
    if (datos.hoy !== undefined) panelHoy.innerHTML = datos.hoy;
    if (datos.opciones !== undefined) $('#sede').innerHTML = datos.opciones;
}

/**
 * Único punto de salida al servidor. Devuelve los datos ya pintados, o deja
 * el mensaje en el hueco del error.
 */
async function enviar(url, cuerpo, { boton } = {}) {
    const textoOriginal = boton?.textContent;

    if (boton) {
        boton.disabled = true;
        boton.textContent = 'Un momento…';
    }

    try {
        const respuesta = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: cuerpo,
        });

        if (respuesta.ok) {
            pintar(await respuesta.json());
            if (error) error.textContent = '';
            return true;
        }

        if (respuesta.status === 422) {
            const { errors } = await respuesta.json();
            if (error) error.textContent = Object.values(errors ?? {})[0]?.[0] ?? 'Revisa el dato.';
            return false;
        }

        if (respuesta.status === 419) {
            if (error) error.textContent = 'La página caducó. Recárgala e intenta de nuevo.';
            return false;
        }

        if (error) error.textContent = 'No pudimos consultar. Intenta de nuevo.';
        return false;
    } catch (fallo) {
        console.error('Falló la consulta del mostrador.', fallo);
        if (error) error.textContent = 'No hay conexión. Revisa tu internet e intenta de nuevo.';
        return false;
    } finally {
        if (boton) {
            boton.disabled = false;
            boton.textContent = textoOriginal;
        }
    }
}

/** Buscar por código o DNI. */
formCodigo?.addEventListener('submit', (evento) => {
    evento.preventDefault();
    enviar(formCodigo.action, new FormData(formCodigo), {
        boton: formCodigo.querySelector('button[type="submit"]'),
    });
});

/** Cambio de laboratorio o de sede: se envía solo, sin botón. */
formSede?.addEventListener('change', () => {
    enviar(formSede.action, new FormData(formSede));
});

/**
 * "Muestra tomada". Va delegado porque ese formulario lo acaba de inyectar
 * el servidor y no existía cuando cargó la página.
 */
document.addEventListener('submit', (evento) => {
    const form = evento.target.closest('[data-form-marcar]');
    if (!form) return;

    evento.preventDefault();
    enviar(form.action, new FormData(form), {
        boton: form.querySelector('button[type="submit"]'),
    });
});

/* ---------------------------------------------------------------- cámara --*/

let stream = null;
let buscando = false;
let lienzo = null;

/**
 * BarcodeDetector es nativo y va mucho más rápido, pero hoy solo está en
 * Chrome y Android. jsQR es el plan B y funciona en todos lados, incluido
 * Safari de iPhone.
 */
const detector = 'BarcodeDetector' in window
    ? new window.BarcodeDetector({ formats: ['qr_code'] })
    : null;

function avisar(texto) {
    if (estadoCamara) estadoCamara.textContent = texto;
}

async function leerCuadro() {
    if (!buscando) return null;

    if (detector) {
        try {
            const encontrados = await detector.detect(video);
            if (encontrados?.[0]?.rawValue) return encontrados[0].rawValue;
        } catch {
            // Algunos navegadores lo anuncian y luego fallan: sigue con jsQR.
        }
    }

    const ancho = video.videoWidth;
    const alto = video.videoHeight;
    if (!ancho || !alto) return null;

    // Se reduce el cuadro antes de analizarlo: a tamaño completo cada vuelta
    // cuesta demasiado y la cámara se ve a tirones.
    const escala = Math.min(1, 640 / Math.max(ancho, alto));
    const w = Math.round(ancho * escala);
    const h = Math.round(alto * escala);

    lienzo ??= document.createElement('canvas');
    lienzo.width = w;
    lienzo.height = h;

    const ctx = lienzo.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(video, 0, 0, w, h);

    const leido = jsQR(ctx.getImageData(0, 0, w, h).data, w, h, {
        inversionAttempts: 'dontInvert',
    });

    return leido?.data ?? null;
}

async function bucle() {
    while (buscando) {
        const texto = await leerCuadro();

        if (texto) {
            cerrarCamara();
            campo.value = texto.trim();
            await enviar(formCodigo.action, new FormData(formCodigo));
            campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // Una pausa corta entre lecturas: deja respirar al navegador y basta
        // de sobra para un código que está quieto delante de la cámara.
        await new Promise((listo) => setTimeout(listo, 120));
    }
}

async function abrirCamara() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
        });
    } catch (fallo) {
        console.error('No se pudo abrir la cámara.', fallo);
        caja.hidden = false;
        avisar(
            fallo?.name === 'NotAllowedError'
                ? 'No diste permiso para la cámara. Habilítala en el navegador o escribe el código a mano.'
                : 'No pudimos abrir la cámara. Escribe el código o el DNI a mano.',
        );
        return;
    }

    video.srcObject = stream;
    await video.play();

    caja.hidden = false;
    botonAbrir.hidden = true;
    buscando = true;
    avisar('Apunta al código QR de la clienta.');
    bucle();
}

function cerrarCamara() {
    buscando = false;
    stream?.getTracks().forEach((pista) => pista.stop());
    stream = null;
    video.srcObject = null;
    caja.hidden = true;
    botonAbrir.hidden = false;
}

botonAbrir?.addEventListener('click', abrirCamara);
botonCerrar?.addEventListener('click', cerrarCamara);

// Si la pestaña se va al fondo, soltar la cámara: el navegador congela el
// vídeo igual y deja la luz encendida.
document.addEventListener('visibilitychange', () => {
    if (document.hidden && stream) cerrarCamara();
});

/**
 * El botón de la cámara nace oculto: solo se enseña si el navegador la tiene.
 * getUserMedia además exige HTTPS (o localhost), así que en una IP de la red
 * local sin certificado no existe y el botón no aparece.
 */
if (navigator.mediaDevices?.getUserMedia && botonAbrir) {
    botonAbrir.hidden = false;
}

// El lector USB de mostrador escribe el código y manda un Enter: eso ya lo
// recoge el submit del formulario. Solo hay que mantener el foco a mano.
campo?.focus();
