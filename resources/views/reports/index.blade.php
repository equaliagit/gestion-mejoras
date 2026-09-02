@extends('layouts.app')

@section('titulo', 'Informes · Buzón de Mejora')

@section('contenido')
    @php
        $meses = $informe->porMes();
        $embudo = $informe->embudo();
        $areas = $informe->porArea();
        $dias = $informe->diasHastaLaDecision();
        $tendencia = collect($informe->tendenciaDeLaDecision())->filter()->values();
        $participacion = $informe->participacion();
    @endphp

    <div class="topbar">
        <div class="grow">
            <h1>Informes</h1>
            <p class="sub">
                Año {{ $anio }} ·
                {{ $informe->total() }} {{ $informe->total() === 1 ? 'propuesta enviada' : 'propuestas enviadas' }}
            </p>
        </div>
        <form method="GET" action="{{ route('reports.index') }}" style="display:flex; gap:8px; align-items:center">
            <label for="anio" class="visually-hidden">Año</label>
            <select id="anio" name="anio" onchange="this.form.submit()" style="width:auto">
                @foreach ($anios as $opcion)
                    <option value="{{ $opcion }}" @selected($opcion === $anio)>{{ $opcion }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn-sm">Ver</button></noscript>
        </form>
        <a class="btn btn-sm" href="{{ route('reports.export', ['anio' => $anio]) }}">Descargar en Excel</a>
    </div>

    @if ($informe->total() === 0)
        <div class="empty">
            <h2>Todavía no hay datos de {{ $anio }}</h2>
            <p>Los informes se llenan solos a medida que la gente propone y el comité decide. No hay nada que rellenar a mano.</p>
        </div>
    @else
        {{-- ------------------------------------------------------ Indicadores --}}
        <div class="cifras">
            <div class="block">
                <div class="block-h"><span>Tiempo hasta la decisión</span></div>
                <div class="block-b">
                    <div class="stat">
                        <div>
                            <span class="big">{{ $dias === null ? '—' : (int) $dias }}</span>
                            <span class="unit">{{ $dias === null ? 'sin decisiones aún' : 'días de media' }}</span>
                            @if ($tendencia->count() > 1)
                                @php
                                    $delta = (int) round($tendencia->first() - $tendencia->last());
                                    $magnitud = abs($delta);
                                @endphp
                                @if ($magnitud === 0)
                                    <p class="hint" style="margin-top:6px">Igual que al principio del año.</p>
                                @else
                                    <p class="trend {{ $delta > 0 ? '' : 'peor' }}">
                                        {{ $delta > 0 ? '▼' : '▲' }}
                                        {{ $magnitud }} {{ $magnitud === 1 ? 'día' : 'días' }}
                                        {{ $delta > 0 ? 'menos' : 'más' }} que al principio del año
                                    </p>
                                @endif
                            @endif
                        </div>
                        @if ($tendencia->count() > 1)
                            @php
                                $max = $tendencia->max();
                                $min = $tendencia->min();
                                $rango = max($max - $min, 1);
                                $paso = $tendencia->count() > 1 ? 128 / ($tendencia->count() - 1) : 0;
                                $puntos = $tendencia
                                    ->map(fn ($v, $i) => round(2 + $i * $paso, 1).','.round(30 - (($v - $min) / $rango) * 24, 1))
                                    ->implode(' ');
                                $ultimo = explode(',', $tendencia
                                    ->map(fn ($v, $i) => round(2 + $i * $paso, 1).','.round(30 - (($v - $min) / $rango) * 24, 1))
                                    ->last());
                            @endphp
                            <div class="spark chart">
                                <svg viewBox="0 0 132 34" role="img"
                                     aria-label="Evolución del tiempo medio de decisión durante el año">
                                    <polyline points="{{ $puntos }}" fill="none" stroke="var(--c1)"
                                              stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                                    <circle cx="{{ $ultimo[0] }}" cy="{{ $ultimo[1] }}" r="4"
                                            fill="var(--c1)" stroke="var(--surface)" stroke-width="2"></circle>
                                </svg>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-h"><span>Se han implantado</span></div>
                <div class="block-b">
                    <div class="stat">
                        <div>
                            <span class="big">{{ $informe->porcentajeImplantadas() }}</span><span class="unit">%</span>
                            <p class="hint" style="margin-top:6px">
                                {{ $embudo['Implantadas'] }} de las {{ $informe->total() }} enviadas
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="block">
                <div class="block-h"><span>Participación</span></div>
                <div class="block-b">
                    <div class="stat">
                        <div>
                            <span class="big">{{ $participacion['personas'] }}</span>
                            <span class="unit">de {{ $participacion['plantilla'] }} personas</span>
                            <p class="hint" style="margin-top:6px">han propuesto algo este año</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="chartgrid">
            {{-- ------------------------------------------- Propuestas por mes --}}
            @php
                $tope = max(collect($meses)->max('registradas'), 1);
                $ancho = max(count($meses), 1);
                $paso = 510 / $ancho;
                $barra = min(18, max(8, $paso / 3.2));
            @endphp
            <div class="block wide chart">
                <div class="block-h"><span>Propuestas por mes</span></div>
                <div class="keys">
                    <span><i class="k1"></i>Registradas</span>
                    <span><i class="k2"></i>Implantadas</span>
                </div>
                <div class="block-b" style="padding-top:0">
                    <svg viewBox="0 0 560 200" role="img"
                         aria-label="Propuestas registradas e implantadas mes a mes durante {{ $anio }}">
                        <defs>
                            <clipPath id="clipCols"><rect x="0" y="0" width="560" height="168"></rect></clipPath>
                        </defs>

                        @foreach ([0, 0.5, 1] as $fraccion)
                            @php $y = round(168 - $fraccion * 156, 1); @endphp
                            <line class="{{ $fraccion === 0 ? 'axisline' : 'gridline' }}"
                                  x1="38" y1="{{ $y }}" x2="552" y2="{{ $y }}"></line>
                            <text x="30" y="{{ $y + 3 }}" text-anchor="end">{{ round($tope * $fraccion) }}</text>
                        @endforeach

                        <g clip-path="url(#clipCols)">
                            @foreach ($meses as $i => $mes)
                                @php
                                    $centro = 40 + $i * $paso + $paso / 2;
                                    $h1 = round($mes['registradas'] / $tope * 156, 1);
                                    $h2 = round($mes['implantadas'] / $tope * 156, 1);
                                @endphp
                                <rect class="s1" x="{{ round($centro - $barra - 1, 1) }}" y="{{ round(168 - $h1, 1) }}"
                                      width="{{ round($barra, 1) }}" height="{{ $h1 + 4 }}" rx="4">
                                    <title>{{ $mes['mes'] }} · {{ $mes['registradas'] }} registradas</title>
                                </rect>
                                <rect class="s2" x="{{ round($centro + 1, 1) }}" y="{{ round(168 - $h2, 1) }}"
                                      width="{{ round($barra, 1) }}" height="{{ $h2 + 4 }}" rx="4">
                                    <title>{{ $mes['mes'] }} · {{ $mes['implantadas'] }} implantadas</title>
                                </rect>
                            @endforeach
                        </g>

                        @foreach ($meses as $i => $mes)
                            <text x="{{ round(40 + $i * $paso + $paso / 2, 1) }}" y="184" text-anchor="middle">
                                {{ $mes['mes'] }}
                            </text>
                        @endforeach
                    </svg>
                </div>
            </div>

            {{-- --------------------------------------------------- El embudo --}}
            <div class="block chart">
                <div class="block-h"><span>Dónde se quedan por el camino</span></div>
                <div class="block-b">
                    <svg viewBox="0 0 400 176" role="img"
                         aria-label="Embudo: {{ collect($embudo)->map(fn ($v, $k) => "$k $v")->implode(', ') }}">
                        @php $topeEmbudo = max($embudo['Registradas'], 1); @endphp
                        @foreach ($embudo as $etapa => $cuantas)
                            @php
                                $y = 8 + $loop->index * 32;
                                $w = round($cuantas / $topeEmbudo * 260, 1);
                            @endphp
                            <text class="cat" x="0" y="{{ $y + 12 }}">{{ $etapa }}</text>
                            <rect class="s1" x="96" y="{{ $y }}" width="{{ max($w, 2) }}" height="16" rx="4">
                                <title>{{ $etapa }} · {{ $cuantas }}</title>
                            </rect>
                            <text class="val" x="{{ 96 + max($w, 2) + 7 }}" y="{{ $y + 12 }}">{{ $cuantas }}</text>
                        @endforeach
                        <line class="axisline" x1="96" y1="0" x2="96" y2="160"></line>
                    </svg>
                </div>
            </div>

            {{-- ------------------------------------------------ Por área --}}
            <div class="block chart">
                <div class="block-h"><span>Propuestas por área</span></div>
                <div class="block-b">
                    @php $topeArea = max(collect($areas)->max('total') ?? 1, 1); @endphp
                    <svg viewBox="0 0 400 {{ max(count($areas) * 28 + 10, 40) }}" role="img"
                         aria-label="Propuestas por área: {{ collect($areas)->map(fn ($a) => $a['area'].' '.$a['total'])->implode(', ') }}">
                        @foreach ($areas as $i => $area)
                            @php
                                $y = 6 + $i * 28;
                                $w = round($area['total'] / $topeArea * 260, 1);
                            @endphp
                            <text class="cat" x="0" y="{{ $y + 11 }}">{{ Str::limit($area['area'], 12) }}</text>
                            <rect class="s1" x="88" y="{{ $y }}" width="{{ max($w, 2) }}" height="14" rx="4">
                                <title>{{ $area['area'] }} · {{ $area['total'] }}</title>
                            </rect>
                            <text class="val" x="{{ 88 + max($w, 2) + 7 }}" y="{{ $y + 11 }}">{{ $area['total'] }}</text>
                        @endforeach
                        <line class="axisline" x1="88" y1="0" x2="88" y2="{{ max(count($areas) * 28, 20) }}"></line>
                    </svg>
                </div>
            </div>
        </div>

        <p class="hint" style="margin-top:18px; max-width:70ch">
            Todos estos números salen del historial de estados. Nadie los teclea, y por eso no pueden estar
            desactualizados: si una propuesta se movió, el informe ya lo sabe.
        </p>
    @endif
@endsection
