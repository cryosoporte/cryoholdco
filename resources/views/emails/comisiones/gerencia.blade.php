@extends('emails.comisiones.layout')

@section('titulo', 'Comisiones pendientes de aprobación')

@section('contenido')
    <h1 style="margin:0 0 16px; color:#1F3A5F; font-size:22px; line-height:28px;">Comisiones de {{ $liquidacion['periodo'] }}</h1>
    <p style="margin:0 0 12px;">Hola, Gerencia:</p>
    <p style="margin:0 0 18px;">
        Se procesaron las comisiones de {{ $liquidacion['periodo'] }} con la base
        {{ $liquidacion['base']['fuente'] }} ({{ number_format($liquidacion['base']['filas']) }} filas).
        El total a pagar es <strong>S/ {{ number_format($liquidacion['total'], 2) }}</strong>
        para {{ $liquidacion['colaboradores_con_comision'] }} de {{ $liquidacion['colaboradores'] }} colaboradores.
    </p>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse; font-size:13px; line-height:18px;">
        <thead>
            <tr style="background-color:#EDF2F8; color:#1F3A5F;">
                <th scope="col" align="left">Cargo</th>
                <th scope="col" align="right">Con comisión</th>
                <th scope="col" align="right">Total S/</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($liquidacion['resumen_cargos'] as $cargo)
                <tr>
                    <td style="border-bottom:1px solid #E4E7EC;">{{ $cargo['cargo'] }}</td>
                    <td align="right" style="border-bottom:1px solid #E4E7EC;">{{ $cargo['con_comision'] }} de {{ $cargo['colaboradores'] }}</td>
                    <td align="right" style="border-bottom:1px solid #E4E7EC; white-space:nowrap;">S/ {{ number_format($cargo['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color:#F7F8FA; font-weight:bold;">
                <th scope="row" align="left">Total</th>
                <td align="right">{{ $liquidacion['colaboradores_con_comision'] }} de {{ $liquidacion['colaboradores'] }}</td>
                <td align="right" style="white-space:nowrap;">S/ {{ number_format($liquidacion['total'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <h2 style="margin:22px 0 8px; color:#1F3A5F; font-size:16px;">Puntos a revisar</h2>
    <ul style="margin:0 0 22px; padding-left:20px;">
        @foreach ($liquidacion['puntos_revision'] as $punto)
            <li style="margin-bottom:6px;">{{ $punto }}</li>
        @endforeach
    </ul>

    <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td style="background-color:#1F3A5F; border-radius:6px; mso-padding-alt:12px 18px;">
                <a href="{{ $enlaceDetalle }}" style="display:inline-block; color:#FFFFFF; padding:12px 18px; font-weight:bold; text-decoration:none;">Revisar y aprobar en el sistema</a>
            </td>
        </tr>
    </table>
@endsection

@section('pie')
    Procesado por {{ $procesadoPor }} el {{ $fechaProceso }}. Este correo se generó automáticamente.
@endsection
