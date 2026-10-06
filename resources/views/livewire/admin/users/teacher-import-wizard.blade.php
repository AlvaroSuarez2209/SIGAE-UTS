<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="page-title">Importar docentes</h1>
        <a href="{{ route('admin.users.index') }}" class="btn-text text-text-secondary">Volver a Usuarios</a>
    </div>

    @php
        $wizardSteps = ['upload' => 'Subir archivo', 'preview' => 'Revisar', 'result' => 'Resumen'];
        $wizardStepKeys = array_keys($wizardSteps);
        $currentStepIndex = array_search($step, $wizardStepKeys);
    @endphp

    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
        @foreach ($wizardSteps as $key => $label)
            @php $stepIndex = array_search($key, $wizardStepKeys); @endphp
            <div class="flex items-center gap-2 {{ $stepIndex <= $currentStepIndex ? 'font-medium text-brand-primary' : 'text-text-secondary' }}">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs
                    {{ $stepIndex <= $currentStepIndex ? 'border-brand-primary bg-brand-primary text-white' : 'border-border-subtle' }}">
                    {{ $stepIndex + 1 }}
                </span>
                Paso {{ $stepIndex + 1 }} de {{ count($wizardSteps) }}: {{ $label }}
            </div>
            @if (! $loop->last)
                <span class="h-px w-6 shrink-0 bg-border-subtle"></span>
            @endif
        @endforeach
    </div>

    @if ($step === 'upload')
        <div class="card space-y-6 p-6">
            <div class="flex flex-col items-start justify-between gap-4 rounded-md bg-surface-muted p-4 sm:flex-row sm:items-center">
                <div>
                    <p class="text-sm font-medium text-text-primary">¿Primera vez importando docentes?</p>
                    <p class="text-sm text-text-secondary">
                        Descarga la plantilla con las columnas requeridas, instrucciones y un ejemplo diligenciado.
                    </p>
                </div>
                <a href="{{ route('admin.users.import.template') }}" class="btn-secondary shrink-0">Descargar plantilla</a>
            </div>

            <form wire:submit="preview" class="space-y-4">
                <div>
                    <label class="field-label">Archivo (.xlsx o .csv)</label>
                    <x-file-dropzone wire-model="file" accept=".xlsx,.csv,.txt" class="mt-2" />
                    @error('file')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                    @if ($fileError)
                        <p class="field-error">{{ $fileError }}</p>
                    @endif
                    @if ($file)
                        <p class="field-help flex items-center gap-1.5">
                            <x-icon name="paperclip" class="h-3.5 w-3.5 shrink-0" />
                            {{ $file->getClientOriginalName() }}
                        </p>
                    @endif
                    <p class="field-help">Máximo 500 filas por archivo. No cambies los nombres de las columnas de la plantilla.</p>
                </div>

                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="preview">
                    <span wire:loading.remove wire:target="preview">Previsualizar</span>
                    <span wire:loading wire:target="preview">Procesando...</span>
                </button>
            </form>
        </div>
    @elseif ($step === 'preview')
        <div class="space-y-6">
            <div class="card p-6">
                <p class="text-sm text-text-secondary">
                    Archivo: <span class="font-medium text-text-primary">{{ $originalFileName }}</span>
                    — {{ count($rows) }} fila(s) leídas,
                    <span class="font-medium text-status-success">{{ $validCount }} válida(s)</span>,
                    <span class="font-medium text-status-error">{{ $invalidCount }} con error</span>.
                </p>
            </div>

            <div class="table-shell">
                <table class="min-w-full divide-y divide-border-subtle">
                    <thead>
                        <tr>
                            <th class="table-header-cell">Fila</th>
                            <th class="table-header-cell">Documento</th>
                            <th class="table-header-cell">Nombre</th>
                            <th class="table-header-cell">Correo</th>
                            <th class="table-header-cell">Programa</th>
                            <th class="table-header-cell">Roles</th>
                            <th class="table-header-cell">Estado</th>
                            <th class="table-header-cell">Acción</th>
                            <th class="table-header-cell">Validación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        @php
                            $actionLabels = [
                                'create' => 'Crear',
                                'update' => 'Actualizar',
                                'skip' => 'Sin cambios',
                                'reject' => 'Rechazada',
                            ];
                        @endphp
                        @foreach ($rows as $row)
                            <tr wire:key="import-row-{{ $row['row_number'] }}" class="table-row">
                                <td class="table-cell">{{ $row['row_number'] }}</td>
                                <td class="table-cell text-text-secondary">{{ $row['data']['tipo_documento'] }} {{ $row['data']['numero_documento'] }}</td>
                                <td class="table-cell">{{ $row['data']['nombre_completo'] }}</td>
                                <td class="table-cell text-text-secondary">{{ $row['data']['correo_institucional'] }}</td>
                                <td class="table-cell text-text-secondary">{{ $row['data']['codigo_programa'] }}</td>
                                <td class="table-cell text-text-secondary">{{ $row['data']['roles'] }}</td>
                                <td class="table-cell text-text-secondary">{{ ucfirst($row['data']['estado']) }}</td>
                                <td class="table-cell text-text-secondary">{{ $actionLabels[$row['action']] ?? '—' }}</td>
                                <td class="table-cell">
                                    @if ($row['errors'] === [])
                                        <span class="inline-flex items-center gap-1 text-status-success">
                                            <x-icon name="check-circle" class="h-4 w-4 shrink-0" /> Válida
                                        </span>
                                    @else
                                        <span class="text-status-error">{{ implode(' ', $row['errors']) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="$dispatch('confirm-modal', {
                        title: 'Confirmar importación',
                        body: 'Vas a crear o actualizar {{ $validCount }} cuenta(s) de docente a partir de este archivo. ¿Continuar?',
                        confirmLabel: 'Confirmar importación',
                        variant: 'warning',
                        action: () => $wire.confirm(),
                    })"
                    class="btn-primary"
                    wire:loading.attr="disabled"
                    wire:target="confirm"
                    @if ($validCount === 0) disabled @endif
                >
                    <span wire:loading.remove wire:target="confirm">Confirmar importación ({{ $validCount }})</span>
                    <span wire:loading wire:target="confirm">Importando...</span>
                </button>
                <button type="button" wire:click="startOver" class="btn-text text-text-secondary">Cancelar y subir otro archivo</button>
            </div>
        </div>
    @elseif ($step === 'result')
        <div class="card space-y-6 p-6">
            <h2 class="text-lg font-semibold text-text-primary">Resumen de la importación</h2>

            <div class="grid grid-cols-2 gap-5 sm:grid-cols-4">
                <x-kpi-card :value="$summary['created']" label="Creadas" color="success" icon="check-circle" />
                <x-kpi-card :value="$summary['updated']" label="Actualizadas" color="primary" icon="pencil" />
                <x-kpi-card :value="$summary['skipped']" label="Omitidas" color="neutral" />
                <x-kpi-card :value="$summary['rejected']" label="Rechazadas" color="error" icon="x-circle" />
            </div>

            @if ($summary['created'] > 0)
                <p class="text-sm text-text-secondary">
                    Los docentes creados recibirán un correo para establecer su propia contraseña.
                </p>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                @if ($summary['rejected'] > 0)
                    <button type="button" wire:click="downloadRejected" class="btn-secondary">
                        Descargar reporte de rechazadas
                    </button>
                @endif
                <a href="{{ route('admin.users.index') }}" class="btn-primary">Ir a Usuarios</a>
                <button type="button" wire:click="startOver" class="btn-text text-text-secondary">Importar otro archivo</button>
            </div>
        </div>
    @endif
</div>
