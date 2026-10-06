<div>
    <div class="mb-6">
        <h1 class="page-title">Vista jerárquica de catálogos</h1>
        <p class="section-subtitle">
            Componentes, sus subcomponentes y las actividades de cada uno, de solo lectura — para crear o editar, usa los
            listados de Componentes, Subcomponentes o Actividades.
        </p>
    </div>

    <div class="space-y-4">
        @forelse ($components as $componentRow)
            <div class="card p-5" wire:key="hierarchy-component-{{ $componentRow->id }}">
                <div class="flex items-center gap-3">
                    {{-- Ícono distinto por nivel (inbox/list/document) para que
                         el tipo de fila se reconozca sin leer la sangría —
                         reutilizo íconos ya existentes y verificados en vez de
                         agregar paths SVG nuevos sin poder probarlos visualmente. --}}
                    <x-icon name="inbox" class="h-5 w-5 shrink-0 text-brand-primary" />
                    <span class="font-semibold text-text-primary">{{ $componentRow->name }}</span>
                    @unless ($componentRow->is_active)
                        <x-active-badge :active="false" />
                    @endunless
                </div>

                @if ($componentRow->subcomponents->isEmpty() && $componentRow->activities->isEmpty())
                    <p class="mt-3 pl-8 text-sm text-text-secondary">Sin subcomponentes ni actividades registradas.</p>
                @else
                    <ul class="mt-3 space-y-1 border-l border-border-subtle pl-6">
                        @foreach ($componentRow->subcomponents as $subcomponent)
                            {{-- Colapsado por defecto (open: false) — clic en la
                                 fila o en el chevron despliega sus actividades.
                                 Estado puramente de presentación, resuelto en el
                                 cliente con Alpine (vía Livewire), sin ida y
                                 vuelta al servidor. --}}
                            <li wire:key="hierarchy-subcomponent-{{ $subcomponent->id }}" x-data="{ open: false }">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="flex w-full items-center gap-2 rounded-md py-1 text-left hover:bg-surface-muted"
                                    :aria-expanded="open.toString()"
                                >
                                    <span class="inline-flex shrink-0 transition-transform duration-150" :class="{ 'rotate-90': open }">
                                        <x-icon name="chevron-right" class="h-3.5 w-3.5 text-text-secondary" />
                                    </span>
                                    <x-icon name="list" class="h-4 w-4 shrink-0 text-text-secondary" />
                                    <span class="text-text-primary">{{ $subcomponent->name }}</span>
                                    @unless ($subcomponent->is_active)
                                        <x-active-badge :active="false" />
                                    @endunless
                                </button>

                                @if ($subcomponent->activities->isNotEmpty())
                                    <ul
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-150"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-cloak
                                        class="ml-[1.125rem] space-y-1 border-l border-border-subtle py-1 pl-6"
                                    >
                                        @foreach ($subcomponent->activities as $activity)
                                            <li wire:key="hierarchy-activity-{{ $activity->id }}" class="flex items-center gap-2">
                                                <x-icon name="document" class="h-3.5 w-3.5 shrink-0 text-text-secondary" />
                                                <span class="text-sm text-text-secondary">{{ $activity->name }}</span>
                                                @unless ($activity->is_active)
                                                    <x-active-badge :active="false" />
                                                @endunless
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @endforeach

                        @foreach ($componentRow->activities as $activity)
                            <li wire:key="hierarchy-direct-activity-{{ $activity->id }}" class="flex items-center gap-2 py-1">
                                <x-icon name="document" class="ml-[1.125rem] h-3.5 w-3.5 shrink-0 text-text-secondary" />
                                <span class="text-sm text-text-secondary">{{ $activity->name }}</span>
                                <span class="text-xs text-text-secondary">(sin subcomponente)</span>
                                @unless ($activity->is_active)
                                    <x-active-badge :active="false" />
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <x-empty-state icon="list" title="No hay componentes registrados" description='Crea el primero desde "Componentes".' />
        @endforelse
    </div>
</div>
