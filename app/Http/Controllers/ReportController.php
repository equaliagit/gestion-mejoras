<?php

namespace App\Http\Controllers;

use App\Services\ProposalReport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La pantalla de informes y su descarga.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $anio = (int) $request->query('anio', now()->year);
        $informe = new ProposalReport($anio);

        return view('reports.index', [
            'informe' => $informe,
            'anio' => $anio,
            'anios' => ProposalReport::aniosConDatos(),
        ]);
    }

    /**
     * El listado en CSV, que Excel abre con doble clic.
     *
     * Se envía en trozos según se genera (streamed) en lugar de armar el
     * archivo entero en memoria. Con cien propuestas da igual, pero es la
     * forma correcta y no cuesta más escribirla.
     */
    public function export(Request $request): StreamedResponse
    {
        $anio = (int) $request->query('anio', now()->year);
        $filas = (new ProposalReport($anio))->filasParaExportar();
        $nombre = "propuestas-{$anio}.csv";

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');

            // Marca de UTF-8 para que Excel no destroce las tildes.
            fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($filas === []) {
                fputcsv($salida, ['Sin propuestas en este periodo'], ';');
                fclose($salida);

                return;
            }

            fputcsv($salida, array_keys($filas[0]), ';');

            foreach ($filas as $fila) {
                fputcsv($salida, $fila, ';');
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
