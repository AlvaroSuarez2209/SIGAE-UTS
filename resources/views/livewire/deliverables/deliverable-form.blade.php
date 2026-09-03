<div class="max-w-2xl">
    <h1 class="mb-6 text-lg font-semibold text-gray-800">
        {{ $deliverable ? 'Editar entregable' : 'Nuevo entregable' }}
    </h1>

    @if ($periodLocked)
        <div class="mb-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800">
            Este periodo está cerrado o archivado. El entregable se muestra en modo de solo consulta.
        </div>
    @endif

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-6 shadow">
        @if (! $deliverable && $templates->isNotEmpty())
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Cargar desde plantilla <span class="font-normal text-gray-400">(opcional)</span>
                </label>
                <select wire:model.live="template_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Sin plantilla — llenar manualmente</option>
                    @foreach ($templates as $tpl)
                        <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <span class="block text-sm font-medium text-gray-700">¿A qué está ligado este entregable?</span>
            <div class="mt-2 flex gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="scope_type" wire:model.live="scope_type" value="activity" @if ($periodLocked) disabled @endif>
                    Una actividad de la distribución
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="scope_type" wire:model.live="scope_type" value="cross_cutting" @if ($periodLocked) disabled @endif>
                    Compromiso transversal (sin actividad)
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Periodo académico</label>
            <select wire:model.live="academic_period_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                <option value="">Selecciona un periodo</option>
                @foreach ($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->name }} ({{ $period->status->label() }})</option>
                @endforeach
            </select>
            @error('academic_period_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @if ($scope_type === 'activity')
            <div>
                <label class="block text-sm font-medium text-gray-700">Actividad</label>
                <select wire:model.live="activity_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                    <option value="">Selecciona una actividad</option>
                    @foreach ($activities as $activity)
                        <option value="{{ $activity->id }}">
                            {{ $activity->component->name }}@if ($activity->subcomponent) / {{ $activity->subcomponent->name }}@endif
                            — {{ $activity->name }}
                        </option>
                    @endforeach
                </select>
                @error('activity_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @else
            <div>
                <label class="block text-sm font-medium text-gray-700">Clasificación del compromiso</label>
                <select wire:model="cross_cutting_commitment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                    <option value="">Selecciona una clasificación</option>
                    @foreach ($commitments as $commitment)
                        <option value="{{ $commitment->id }}">{{ $commitment->name }}</option>
                    @endforeach
                </select>
                @error('cross_cutting_commitment_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre</label>
            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Instrucciones <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="instructions" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Criterio de cumplimiento <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="completion_criteria" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de periodicidad</label>
            <select wire:model="periodicity_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                @foreach ($periodicityOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Apertura</label>
                <input type="datetime-local" wire:model="opens_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                @error('opens_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha límite</label>
                <input type="datetime-local" wire:model="due_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                @error('due_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Cierre <span class="font-normal text-gray-400">(opc.)</span></label>
                <input type="datetime-local" wire:model="closes_at" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
                @error('closes_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700">Tipos de evidencia permitidos</span>
            <div class="mt-2 space-y-1">
                @foreach ($evidenceTypeOptions as $option)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="allowed_evidence_types" value="{{ $option->value }}" @if ($periodLocked) disabled @endif class="rounded border-gray-300">
                        {{ $option->label() }}
                    </label>
                @endforeach
            </div>
            @error('allowed_evidence_types') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700">Formatos de archivo permitidos <span class="font-normal text-gray-400">(si aplica)</span></span>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach ($fileTypeOptions as $extension)
                    <label class="flex items-center gap-1 text-sm text-gray-700">
                        <input type="checkbox" wire:model="allowed_file_types" value="{{ $extension }}" @if ($periodLocked) disabled @endif class="rounded border-gray-300">
                        .{{ $extension }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Máximo de archivos</label>
                <input type="number" min="1" wire:model="max_files" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tamaño máximo (MB)</label>
                <input type="number" min="1" wire:model="max_file_size_mb" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">
                Peso porcentual <span class="font-normal text-gray-400">(opcional; si se deja en blanco, pesa igual que los demás obligatorios)</span>
            </label>
            <input type="number" min="1" max="100" wire:model="weight_percentage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @if ($periodLocked) disabled @endif>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_mandatory" id="is_mandatory" class="rounded border-gray-300" @if ($periodLocked) disabled @endif>
            <label for="is_mandatory" class="text-sm text-gray-700">Obligatorio (afecta el % de avance)</label>
        </div>

        <hr class="border-gray-200">

        <div>
            <span class="block text-sm font-medium text-gray-700">Destinatarios</span>
            <div class="mt-2 flex gap-6">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="all" @if ($periodLocked) disabled @endif>
                    Todos los docentes {{ $scope_type === 'activity' ? 'de la actividad' : 'activos' }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="radio" name="recipient_mode" wire:model.live="recipient_mode" value="subset" @if ($periodLocked) disabled @endif>
                    Seleccionar docentes específicos
                </label>
            </div>

            @if ($recipient_mode === 'subset')
                <div class="mt-3 max-h-48 space-y-1 overflow-y-auto rounded-md border border-gray-200 p-3">
                    @forelse ($candidateTeachers as $teacher)
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" wire:model="recipient_ids" value="{{ $teacher->id }}" @if ($periodLocked) disabled @endif class="rounded border-gray-300">
                            {{ $teacher->name }}
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">
                            @if ($scope_type === 'activity')
                                No hay docentes asignados a esta actividad en este periodo todavía.
                            @else
                                No hay docentes disponibles.
                            @endif
                        </p>
                    @endforelse
                </div>
                @error('recipient_ids') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @else
                <p class="mt-2 text-sm text-gray-500">
                    Se incluirán los {{ $candidateTeachers->count() }} docente(s) que cumplen este ámbito al momento de guardar.
                </p>
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2">
            @unless ($periodLocked)
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Guardar
                </button>
            @endunless
            <a href="{{ route('deliverables.index') }}" class="text-sm text-gray-600 hover:underline">
                {{ $periodLocked ? 'Volver' : 'Cancelar' }}
            </a>
        </div>
    </form>
</div>
