<div class="max-w-2xl">
    <h1 class="mb-6 text-lg font-semibold text-gray-800">
        {{ $template ? 'Editar plantilla' : 'Nueva plantilla' }}
    </h1>

    <form wire:submit="save" class="space-y-4 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium text-gray-700">Nombre</label>
            <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Descripción <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Instrucciones <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="instructions" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('instructions') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Criterio de cumplimiento <span class="font-normal text-gray-400">(opcional)</span></label>
            <textarea wire:model="completion_criteria" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('completion_criteria') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Tipo de periodicidad</label>
            <select wire:model="periodicity_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($periodicityOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            @error('periodicity_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700">Tipos de evidencia permitidos</span>
            <div class="mt-2 space-y-1">
                @foreach ($evidenceTypeOptions as $option)
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" wire:model="allowed_evidence_types" value="{{ $option->value }}" class="rounded border-gray-300">
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
                        <input type="checkbox" wire:model="allowed_file_types" value="{{ $extension }}" class="rounded border-gray-300">
                        .{{ $extension }}
                    </label>
                @endforeach
            </div>
            @error('allowed_file_types') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Máximo de archivos</label>
                <input type="number" min="1" wire:model="max_files" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('max_files') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Tamaño máximo (MB)</label>
                <input type="number" min="1" wire:model="max_file_size_mb" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('max_file_size_mb') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">
                Peso porcentual <span class="font-normal text-gray-400">(opcional; si se deja en blanco, pesa igual que los demás obligatorios)</span>
            </label>
            <input type="number" min="1" max="100" wire:model="weight_percentage" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('weight_percentage') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_mandatory" id="is_mandatory" class="rounded border-gray-300">
            <label for="is_mandatory" class="text-sm text-gray-700">Obligatorio</label>
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300">
            <label for="is_active" class="text-sm text-gray-700">Activa</label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Guardar
            </button>
            <a href="{{ route('deliverable-templates.index') }}" class="text-sm text-gray-600 hover:underline">Cancelar</a>
        </div>
    </form>
</div>
