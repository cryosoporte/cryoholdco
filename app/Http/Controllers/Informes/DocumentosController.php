<?php

namespace App\Http\Controllers\Informes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Informes\EnviarDocumentosMasivoRequest;
use App\Http\Requests\Informes\EnviarDocumentosRequest;
use App\Http\Requests\Informes\ListarDocumentosRequest;
use App\Http\Requests\Informes\VerDocumentoRequest;
use App\Http\Requests\Informes\VistaPreviaCorreoRequest;
use App\Informes\AlcanceInformes;
use App\Informes\CarpetaDocumentos;
use App\Informes\ContratosInformes;
use App\Informes\CuerpoCorreoContrato;
use App\Informes\EnviarDocumentosContrato;
use App\Informes\EnviarDocumentosMasivo;
use App\Mail\DocumentosContrato;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentosController extends Controller
{
    /** Tipos que el navegador puede mostrar sin riesgo, con su tipo de contenido fijo; el resto se descarga. */
    private const TIPOS_EN_LINEA = [
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public function index(ListarDocumentosRequest $request, CarpetaDocumentos $carpeta, ContratosInformes $contratos): JsonResponse
    {
        $datos = $request->validated();
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);
        abort_unless($contratos->existe($empresa, $datos['numero_base']), 404, 'El contrato no está en el listado de Informes de tu empresa.');

        return response()->json([
            'archivos' => $carpeta->listar($empresa, $datos['numero_base']),
            'titulares' => $contratos->titulares($empresa, $datos['numero_base']),
            'motivos' => collect(AlcanceInformes::MOTIVOS_NO_APTO)->map(fn (string $etiqueta, string $valor): array => compact('valor', 'etiqueta'))->values(),
        ]);
    }

    public function ver(VerDocumentoRequest $request, CarpetaDocumentos $carpeta, ContratosInformes $contratos): BinaryFileResponse
    {
        $datos = $request->validated();
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);
        abort_unless($contratos->existe($empresa, $datos['numero_base']), 404);

        $archivo = $carpeta->seleccionar($empresa, $datos['numero_base'], [$datos['archivo']])[0];
        $tipo = self::TIPOS_EN_LINEA[strtolower(pathinfo($archivo['nombre'], PATHINFO_EXTENSION))] ?? null;

        $respuesta = $tipo !== null
            ? response()->file($archivo['ruta'], ['Content-Type' => $tipo])->setContentDisposition('inline', $archivo['nombre'], Str::ascii($archivo['nombre']))
            : response()->download($archivo['ruta'], $archivo['nombre'], [], 'attachment');

        $respuesta->setPrivate();
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');

        return $respuesta;
    }

    public function vistaPrevia(VistaPreviaCorreoRequest $request, CuerpoCorreoContrato $cuerpo): JsonResponse
    {
        $datos = $request->validated();
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        $correo = new DocumentosContrato(
            $empresa,
            $datos['numero_base'],
            [],
            $cuerpo->para($empresa, $datos['numero_base'], ['sangre' => $datos['motivo_sangre'] ?? null, 'tejido' => $datos['motivo_tejido'] ?? null]),
        );

        return response()->json([
            'asunto' => $correo->envelope()->subject,
            'html' => $correo->render(),
        ]);
    }

    public function enviar(EnviarDocumentosRequest $request, EnviarDocumentosContrato $documentos): JsonResponse
    {
        $datos = $request->validated();
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        $enviado = $documentos->enviar(
            empresa: $empresa,
            autor: $request->user()->email,
            numeroBase: $datos['numero_base'],
            identificacion: $datos['identificacion'],
            nombres: $datos['archivos'],
            usuario: $request->user()->codigo,
            motivos: ['sangre' => $datos['motivo_sangre'] ?? null, 'tejido' => $datos['motivo_tejido'] ?? null],
        );

        if (! $enviado) {
            return response()->json([
                'message' => 'No se pudieron enviar los documentos. Intenta de nuevo más tarde.',
            ], 502);
        }

        return response()->json(['message' => 'Documentos enviados correctamente.']);
    }

    public function enviarMasivo(EnviarDocumentosMasivoRequest $request, EnviarDocumentosMasivo $masivo): JsonResponse
    {
        $datos = $request->validated();
        $empresa = $request->user()->empresa;
        abort_if(blank($empresa), 403);

        return response()->json($masivo->enviar(
            empresa: $empresa,
            autor: $request->user()->email,
            contratos: $datos['contratos'],
            usuario: $request->user()->codigo,
        ));
    }
}
