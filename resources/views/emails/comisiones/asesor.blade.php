@extends('emails.comisiones.layout')

@section('titulo', 'Detalle de tu comisión')

@section('contenido')
    <h1 style="margin:0 0 16px; color:#1F3A5F; font-size:22px; line-height:28px;">Tu comisión de {{ mb_strtolower($periodo) }}</h1>
    <p style="margin:0 0 12px;">Hola, {{ $asesor['nombre_corto'] }}:</p>
    <p style="margin:0 0 18px;">
        @if ($asesor['total'] > 0)
            Tu comisión de {{ $periodo }} fue aprobada por Gerencia. Este es el detalle por línea:
        @else
            Te compartimos el resultado de {{ $periodo }}. Este mes no se generó comisión; este es tu avance frente a cada cuota:
        @endif
    </p>
    <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-size:12px; line-height:17px;">
        <thead>
            <tr style="background-color:#EDF2F8; color:#1F3A5F;">
                <th scope="col" align="left">Concepto</th>
                <th scope="col" align="left">Logrado</th>
                <th scope="col" align="left">Cuota</th>
                <th scope="col" align="left">Tramo aplicado</th>
                <th scope="col" align="right">Comisión S/</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($asesor['lineas'] as $linea)
                <tr>
                    <td style="border-bottom:1px solid #E4E7EC;">{{ $linea['concepto'] }}</td>
                    <td style="border-bottom:1px solid #E4E7EC;">{{ $linea['logrado'] }}</td>
                    <td style="border-bottom:1px solid #E4E7EC;">{{ $linea['cuota'] }}</td>
                    <td style="border-bottom:1px solid #E4E7EC;">{{ $linea['tramo'] }}</td>
                    <td align="right" style="border-bottom:1px solid #E4E7EC; white-space:nowrap;">S/ {{ number_format($linea['comision'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color:#F7F8FA; font-weight:bold;">
                <th scope="row" align="left" colspan="4">Total {{ $periodo }}</th>
                <td align="right" style="white-space:nowrap;">S/ {{ number_format($asesor['total'], 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <p style="margin:18px 0 0; padding:12px; background-color:#F7F8FA;">{{ $asesor['nota'] }}</p>
@endsection

@section('pie')
    Aprobación del período: {{ $fechaAprobacion }}. El pago se realizará con la planilla del mes.
    Ante cualquier consulta sobre este detalle, responde a este correo. Este correo se generó automáticamente.
@endsection
