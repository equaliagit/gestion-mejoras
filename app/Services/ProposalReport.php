<?php

namespace App\Services;

use App\Enums\Visibility;
use App\Models\Area;
use App\Models\Proposal;
use App\Models\ProposalStatus as Status;
use App\Models\StatusChange;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Los números de la pantalla de informes.
 *
 * Todo sale del historial de estados y de las fechas de la propuesta: no hay
 * ni un dato que alguien tenga que teclear. Por eso insistí tanto en que
 * status_changes no se editara nunca — de su fiabilidad depende esto.
 *
 * Las cuentas se hacen en PHP y no en SQL a propósito. Con el volumen que
 * maneja esta aplicación (cientos de propuestas al año) la diferencia es
 * inapreciable, y a cambio funciona igual en MySQL que en la base de datos
 * en memoria que usan las pruebas, sin depender de funciones de fechas que
 * cada motor escribe a su manera.
 */
class ProposalReport
{
    private Collection $propuestas;

    private Collection $cambios;

    public function __construct(public readonly int $year)
    {
        // Sin el filtro de visibilidad: un informe cuenta todo lo que hay,
        // aunque nunca enseñe de quién es cada propuesta.
        $this->propuestas = Proposal::withoutGlobalScopes()
            ->submitted()
            ->whereYear('submitted_at', $this->year)
            ->with('status')
            ->get();

        $this->cambios = StatusChange::query()
            ->whereIn('proposal_id', $this->propuestas->pluck('id'))
            ->with('toStatus')
            ->get();
    }

    public function total(): int
    {
        return $this->propuestas->count();
    }

    /** Cuántas llegaron alguna vez a este estado, aunque hoy estén en otro. */
    public function alcanzaron(string $codigo): int
    {
        return $this->cambios
            ->filter(fn (StatusChange $c) => $c->toStatus->code === $codigo)
            ->unique('proposal_id')
            ->count();
    }

    /**
     * El embudo: dónde se van quedando por el camino.
     *
     * @return array<string, int>
     */
    public function embudo(): array
    {
        return [
            'Registradas' => $this->total(),
            'Revisadas' => $this->alcanzaron(Status::IN_REVIEW),
            'Al comité' => $this->alcanzaron(Status::IN_COMMITTEE),
            'Aprobadas' => $this->alcanzaron(Status::APPROVED),
            'Implantadas' => $this->alcanzaron(Status::IMPLEMENTED),
        ];
    }

    /**
     * Registradas e implantadas mes a mes.
     *
     * @return list<array{mes: string, registradas: int, implantadas: int}>
     */
    public function porMes(): array
    {
        $hasta = $this->year === (int) now()->year ? (int) now()->month : 12;
        $meses = [];

        for ($mes = 1; $mes <= $hasta; $mes++) {
            $meses[] = [
                'mes' => mb_strtoupper(now()->setDate($this->year, $mes, 1)->translatedFormat('M')),
                'registradas' => $this->propuestas
                    ->filter(fn (Proposal $p) => (int) $p->submitted_at->month === $mes)
                    ->count(),
                'implantadas' => $this->propuestas
                    ->filter(fn (Proposal $p) => $p->closed_on && (int) $p->closed_on->month === $mes)
                    ->count(),
            ];
        }

        return $meses;
    }

    /**
     * Cuántas propuestas ha recibido cada área, de más a menos.
     *
     * @return list<array{area: string, total: int}>
     */
    public function porArea(): array
    {
        $nombres = Area::query()->pluck('name', 'id');

        return $this->propuestas
            ->groupBy('area_id')
            ->map(fn (Collection $grupo, int|string $areaId) => [
                'area' => $nombres[$areaId] ?? 'Sin área',
                'total' => $grupo->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** Días de media desde que se envía hasta que hay decisión. */
    public function diasHastaLaDecision(): ?float
    {
        $decididas = $this->propuestas->filter(fn (Proposal $p) => $p->decided_at !== null);

        if ($decididas->isEmpty()) {
            return null;
        }

        return round($decididas->avg(
            fn (Proposal $p) => $p->submitted_at->startOfDay()->diffInDays($p->decided_at->startOfDay())
        ), 0);
    }

    /**
     * La misma media, mes a mes, para dibujar si la cosa mejora o empeora.
     *
     * @return list<?float>
     */
    public function tendenciaDeLaDecision(): array
    {
        $hasta = $this->year === (int) now()->year ? (int) now()->month : 12;
        $serie = [];

        for ($mes = 1; $mes <= $hasta; $mes++) {
            $delMes = $this->propuestas->filter(
                fn (Proposal $p) => $p->decided_at !== null && (int) $p->decided_at->month === $mes
            );

            $serie[] = $delMes->isEmpty() ? null : round($delMes->avg(
                fn (Proposal $p) => $p->submitted_at->startOfDay()->diffInDays($p->decided_at->startOfDay())
            ), 1);
        }

        return $serie;
    }

    public function porcentajeImplantadas(): int
    {
        if ($this->total() === 0) {
            return 0;
        }

        return (int) round($this->alcanzaron(Status::IMPLEMENTED) * 100 / $this->total());
    }

    /** @return array{personas: int, plantilla: int} */
    public function participacion(): array
    {
        return [
            'personas' => $this->propuestas->pluck('user_id')->unique()->count(),
            'plantilla' => User::query()->where('is_active', true)->count(),
        ];
    }

    /** @return array<string, int> */
    public function porVisibilidad(): array
    {
        return [
            'Públicas' => $this->propuestas->where('visibility', Visibility::Public)->count(),
            'Privadas' => $this->propuestas->where('visibility', Visibility::Private)->count(),
            'Anónimas' => $this->propuestas->where('visibility', Visibility::Anonymous)->count(),
        ];
    }

    /** Los años con propuestas, para el selector de periodo. */
    public static function aniosConDatos(): array
    {
        $anios = Proposal::withoutGlobalScopes()
            ->submitted()
            ->get()
            ->map(fn (Proposal $p) => (int) $p->submitted_at->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $anios === [] ? [(int) now()->year] : $anios;
    }

    /**
     * Las filas del archivo que se descarga. Una por propuesta, sin nombres
     * en las anónimas: un informe no es una puerta trasera.
     *
     * @return list<array<string, string|int|null>>
     */
    public function filasParaExportar(): array
    {
        return Proposal::withoutGlobalScopes()
            ->submitted()
            ->whereYear('submitted_at', $this->year)
            ->with(['area', 'status', 'author', 'reviewer'])
            ->orderBy('reference')
            ->get()
            ->map(fn (Proposal $p) => [
                'Referencia' => $p->reference,
                'Titulo' => $p->title,
                'Area' => $p->area->name,
                'Proponente' => $p->authorName(),
                'Visibilidad' => $p->visibility->label(),
                'Prioridad' => $p->effectivePriority()->label(),
                'Estado' => $p->status->name,
                'Revisor' => $p->reviewer?->name,
                'Enviada' => $p->submitted_at?->format('d/m/Y'),
                'Decidida' => $p->decided_at?->format('d/m/Y'),
                'Cerrada' => $p->closed_on?->format('d/m/Y'),
                'Dias hasta la decision' => $p->decided_at
                    ? (int) $p->submitted_at->startOfDay()->diffInDays($p->decided_at->startOfDay())
                    : null,
            ])
            ->all();
    }
}
