/**
 * Módulo Cliente: consulta el DNI sin recargar la página.
 *
 * La página tiene dos caras y solo se ve una a la vez:
 *   - buscador  → el formulario del DNI.
 *   - resultado → la ficha, con el DNI consultado y "Salir" arriba.
 *
 * Si este archivo no carga, todo sigue funcionando como un POST normal: el
 * controlador devuelve la página entera y el Blade decide qué cara mostrar.
 * Por eso "Salir" es un <a> de verdad; aquí solo le evitamos la recarga.
 */
import { pintarQr } from './qr.js';

const formulario = document.querySelector('[data-form-dni]');
const buscador = document.querySelector('[data-buscador]');
const resultado = document.querySelector('[data-resultado]');
const sesion = document.querySelector('[data-sesion]');
const sesionDni = document.querySelector('[data-sesion-dni]');
const salir = document.querySelector('[data-salir]');
const error = document.querySelector('[data-error-dni]');
const campo = document.querySelector('#dni');
const boton = formulario?.querySelector('button[type="submit"]');
const textoBoton = boton?.textContent.trim();

/** Pasa a la cara del resultado. */
function mostrarFicha(html, dni) {
    resultado.innerHTML = html;
    // El fragmento recién insertado trae el contenedor del QR vacío.
    pintarQr(resultado);

    sesionDni.textContent = dni;
    sesion.classList.remove('hidden');
    buscador.classList.add('hidden');

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/** Vuelve a la cara del buscador, limpia y deja el cursor listo. */
function mostrarBuscador() {
    resultado.innerHTML = '';
    sesion.classList.add('hidden');
    buscador.classList.remove('hidden');

    error.textContent = '';
    if (campo) {
        campo.value = '';
        campo.focus();
    }
}

formulario?.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    error.textContent = '';

    if (boton) {
        boton.disabled = true;
        boton.textContent = 'Consultando…';
    }

    try {
        const respuesta = await fetch(formulario.action, {
            method: 'POST',
            // Esta cabecera es la que hace que el controlador devuelva solo el
            // fragmento, y que los errores de validación lleguen como JSON.
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(formulario),
        });

        if (respuesta.ok) {
            mostrarFicha(await respuesta.text(), campo?.value ?? '');
            return;
        }

        if (respuesta.status === 422) {
            const { errors } = await respuesta.json();
            error.textContent = errors?.dni?.[0] ?? 'Revisa el DNI.';
            return;
        }

        if (respuesta.status === 419) {
            error.textContent = 'La página caducó. Recárgala e intenta de nuevo.';
            return;
        }

        error.textContent = 'No pudimos consultar. Intenta de nuevo.';
    } catch (fallo) {
        console.error('Falló la consulta del DNI.', fallo);
        error.textContent = 'No hay conexión. Revisa tu internet e intenta de nuevo.';
    } finally {
        if (boton) {
            boton.disabled = false;
            boton.textContent = textoBoton;
        }
    }
});

salir?.addEventListener('click', (evento) => {
    evento.preventDefault();
    mostrarBuscador();
});

// Solo dígitos en el campo, para que no llegue basura al servidor.
campo?.addEventListener('input', () => {
    campo.value = campo.value.replace(/\D/g, '');
});

// Cuando el POST es normal (sin fetch), la ficha ya viene en la página.
pintarQr();
