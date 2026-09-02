@extends('layouts.app')

@section('titulo', 'Nueva propuesta · Buzón de Mejora')

@section('contenido')
    <div class="topbar">
        <div class="grow">
            <h1>Nueva propuesta de mejora</h1>
            <p class="sub">Ocho preguntas. Puedes guardarla como borrador y terminarla otro día.</p>
        </div>
    </div>

    @if ($errors->any())
        <p class="flash flash-bad">
            <strong>Faltan cosas por rellenar.</strong> Mira los avisos en rojo bajo cada pregunta.
        </p>
    @endif

    <form method="POST" action="{{ route('proposals.store') }}" class="stack">
        @csrf

        <div class="row">
            <div class="field">
                <label>Proponente</label>
                <input type="text" value="{{ auth()->user()->name }}" disabled>
                <p class="hint">Se rellena con tu usuario. La fecha de registro es hoy, {{ now()->translatedFormat('j \d\e F \d\e Y') }}.</p>
            </div>

            <div class="field">
                <label for="area_id">Área o proceso afectado <span class="req">*</span></label>
                <select id="area_id" name="area_id" required aria-invalid="{{ $errors->has('area_id') ? 'true' : 'false' }}">
                    <option value="">Elige un área…</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->id }}" @selected(old('area_id', auth()->user()->area_id) == $area->id)>
                            {{ $area->name }}
                        </option>
                    @endforeach
                </select>
                @error('area_id') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="field">
            <label for="title">Título de la propuesta <span class="req">*</span></label>
            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="140" required
                   aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}">
            <p class="hint">Una frase que se entienda sin leer el resto.</p>
            @error('title') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="problem">¿Cuál es el problema actual? <span class="req">*</span></label>
            <textarea id="problem" name="problem" required
                      aria-invalid="{{ $errors->has('problem') ? 'true' : 'false' }}">{{ old('problem') }}</textarea>
            <p class="hint">Cuéntalo con tus palabras: qué pasa hoy y por qué es un incordio.</p>
            @error('problem') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="proposal">¿Qué propones? <span class="req">*</span></label>
            <textarea id="proposal" name="proposal" required
                      aria-invalid="{{ $errors->has('proposal') ? 'true' : 'false' }}">{{ old('proposal') }}</textarea>
            <p class="hint">Con el detalle que puedas: qué cambiaría, quién lo usaría y qué haría falta.</p>
            @error('proposal') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label>Impacto esperado <span class="req">*</span></label>
            <div class="choices">
                @foreach ($impactos as $impacto)
                    <label class="choice">
                        <input type="checkbox" name="impacts[]" value="{{ $impacto->id }}"
                               @checked(in_array($impacto->id, old('impacts', [])))>
                        {{ $impacto->name }}
                    </label>
                @endforeach
            </div>
            <p class="hint">Puedes marcar más de uno.</p>
            @error('impacts') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="expected_benefit">¿Qué beneficio esperas conseguir? <span class="req">*</span></label>
            <textarea id="expected_benefit" name="expected_benefit" required
                      aria-invalid="{{ $errors->has('expected_benefit') ? 'true' : 'false' }}">{{ old('expected_benefit') }}</textarea>
            <p class="hint">Si puedes, ponle número: horas ahorradas, errores evitados, días de plazo…</p>
            @error('expected_benefit') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label>Prioridad que le das <span class="req">*</span></label>
            <div class="choices">
                @foreach ($prioridades as $prioridad)
                    <label class="choice">
                        <input type="radio" name="priority" value="{{ $prioridad->value }}"
                               @checked(old('priority', 'medium') === $prioridad->value)>
                        {{ $prioridad->label() }}
                    </label>
                @endforeach
            </div>
            <p class="hint">Es tu criterio. El comité puede ajustarla.</p>
            @error('priority') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="visib">
            <div class="field">
                <label>¿Quién puede ver esta propuesta? <span class="req">*</span></label>
                <div class="choices">
                    @foreach ($visibilidades as $visibilidad)
                        <label class="choice">
                            <input type="radio" name="visibility" value="{{ $visibilidad->value }}"
                                   @checked(old('visibility', 'public') === $visibilidad->value)>
                            {{ $visibilidad->label() }}
                        </label>
                    @endforeach
                </div>
            </div>
            <p class="hint">
                @foreach ($visibilidades as $visibilidad)
                    <strong>{{ $visibilidad->label() }}:</strong> {{ $visibilidad->description() }}
                    @if (! $loop->last) &nbsp; @endif
                @endforeach
            </p>
            @error('visibility') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="formfoot">
            <p class="note">Al enviarla recibirás un correo de confirmación y el comité otro con el aviso.</p>
            <button type="submit" name="accion" value="borrador" class="btn">Guardar borrador</button>
            <button type="submit" name="accion" value="enviar" class="btn btn-pri">Enviar propuesta</button>
        </div>
    </form>
@endsection
