<x-mail::message>
# Recordatorio de serología

Hola, {{ $nombre }}:

Te recordamos que tienes pendiente tu toma de muestra para serología.
Ingresa al portal para consultar la información de tu atención y las sedes disponibles.

<x-mail::button :url="route('clientes.index')">
Consultar mi serología
</x-mail::button>

Si necesitas ayuda para coordinar tu atención, comunícate con nuestro equipo.

Saludos,<br>
Equipo Criocord
</x-mail::message>
