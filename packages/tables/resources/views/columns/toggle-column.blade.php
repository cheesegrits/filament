<div
    x-data="{
        error: undefined,
        state: @js($getState()),
        isLoading: false
    }"
    x-init="
        $watch('state', () => $refs.button.dispatchEvent(new Event('change')))
    "
    {{ $attributes->merge($getExtraAttributes(), escape: false)->class([
        'filament-tables-toggle-column',
    ]) }}
>
    @php
        $offColor = $getOffColor();
        $onColor = $getOnColor();
    @endphp

    <button
        role="switch"
        aria-checked="false"
        x-bind:aria-checked="state.toString()"
        x-on:click="! isLoading && (state = ! state)"
        x-ref="button"
        x-on:change="
            isLoading = true
            response = await $wire.updateTableColumnState(@js($getName()), @js($recordKey), state)
            error = response?.error ?? undefined
            isLoading = false
        "
        x-tooltip="error"
        x-bind:class="
            (state ? '{{ match ($getOnColor()) {
                'danger' => 'bg-danger-600',
                'gray' => 'bg-gray-600',
                'primary', null => 'bg-primary-600',
                'secondary' => 'bg-secondary-600',
                'success' => 'bg-success-600',
                'warning' => 'bg-warning-600',
                default => $onColor,
            } }}' : '{{ match ($getOffColor()) {
                'danger' => 'bg-danger-600',
                'gray' => 'bg-gray-600',
                'primary' => 'bg-primary-600',
                'secondary' => 'bg-secondary-600',
                'success' => 'bg-success-600',
                'warning' => 'bg-warning-600',
                null => 'bg-gray-200 dark:bg-gray-700',
                default => $offColor,
            } }}') +
            (isLoading ? ' opacity-70 pointer-events-none' : '')
        "
        @disabled($isDisabled())
        type="button"
        class="relative inline-flex shrink-0 ml-4 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none disabled:opacity-70 disabled:pointer-events-none"
    >
        <span
            class="pointer-events-none relative inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 ease-in-out transition duration-200"
            x-bind:class="{
                'translate-x-5 rtl:-translate-x-5': state,
                'translate-x-0': ! state,
            }"
        >
            <span
                class="absolute inset-0 h-full w-full flex items-center justify-center transition-opacity"
                aria-hidden="true"
                x-bind:class="{
                    'opacity-0 ease-out duration-100': state,
                    'opacity-100 ease-in duration-200': ! state,
                }"
            >
                @if ($hasOffIcon())
                    <x-filament::icon
                        :name="$getOffIcon()"
                        alias="filament-tables::columns.toggle.off"
                        :color="match ($offColor) {
                            'danger' => 'text-danger-600',
                            'gray' => 'text-gray-600',
                            'primary' => 'text-primary-600',
                            'secondary' => 'text-secondary-600',
                            'success' => 'text-success-600',
                            'warning' => 'text-warning-600',
                            null => 'text-gray-400 dark:text-gray-700',
                            default => $offColor,
                        }"
                        size="h-3 w-3"
                    />
                @endif
            </span>

            <span
                class="absolute inset-0 h-full w-full flex items-center justify-center transition-opacity"
                aria-hidden="true"
                x-bind:class="{
                    'opacity-100 ease-in duration-200': state,
                    'opacity-0 ease-out duration-100': ! state,
                }"
            >
                @if ($hasOnIcon())
                    <x-filament::icon
                        :name="$getOnIcon()"
                        alias="filament-tables::columns.toggle.on"
                        :color="match ($onColor) {
                            'danger' => 'text-danger-600',
                            'gray' => 'text-gray-600',
                            'primary', null => 'text-primary-600',
                            'secondary' => 'text-secondary-600',
                            'success' => 'text-success-600',
                            'warning' => 'text-warning-600',
                            default => $onColor,
                        }"
                        size="h-3 w-3"
                        x-cloak=""
                    />
                @endif
            </span>
        </span>
    </button>
</div>
