<template>
  <div class="space-y-5">
    <div>
      <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Frequency</h3>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          v-for="(label, key) in frequencies"
          :key="key"
          type="button"
          :aria-pressed="!!freqStates[key]"
          :class="[chipBase, chipClass(freqStates[key])]"
          @click="cycleFrequency(String(key))"
        >{{ freqStates[key] === 'exclude' ? 'Not ' : '' }}{{ label }}</button>
      </div>
      <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Click once to include, again to exclude, a third time to clear.</p>
    </div>

    <div>
      <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Occurring in</h3>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          v-for="(name, i) in MONTHS"
          :key="name"
          type="button"
          :aria-pressed="months.includes(i + 1)"
          :class="[chipBase, chipClass(months.includes(i + 1) ? 'include' : undefined)]"
          @click="toggleMonth(i + 1)"
        >{{ name }}</button>
      </div>
      <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Any year, so last year's edition counts for next year. Schedules without dates never match a month.</p>
    </div>

    <div v-if="includesFrequency && months.length" class="flex flex-wrap items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
      <span>Show schedules that match</span>
      <span class="inline-flex overflow-hidden rounded-md border border-gray-300 dark:border-gray-600">
        <button
          v-for="mode in ['all', 'any'] as const"
          :key="mode"
          type="button"
          :aria-pressed="matchMode === mode"
          class="px-3 py-1 text-sm"
          :class="matchMode === mode ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300'"
          @click="matchMode = mode"
        >{{ mode === 'all' ? 'both filters' : 'either filter' }}</button>
      </span>
    </div>

    <div>
      <div class="flex items-baseline justify-between">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Liveness</h3>
        <span class="text-sm text-gray-700 dark:text-gray-300">{{ livenessMin }}/4 &ndash; {{ livenessMax }}/4</span>
      </div>
      <div class="relative mt-2 h-6">
        <div class="absolute inset-x-[9px] top-1/2 h-1 -translate-y-1/2 rounded-full bg-gray-200 dark:bg-gray-600">
          <div
            class="absolute h-1 rounded-full bg-indigo-500"
            :style="{ left: `${livenessMin * 25}%`, width: `${(livenessMax - livenessMin) * 25}%` }"
          ></div>
        </div>
        <input
          type="range" min="0" max="4" step="1"
          :value="livenessMin"
          aria-label="Minimum liveness score"
          class="liveness-range absolute inset-0 w-full"
          :style="{ zIndex: livenessMin === livenessMax && livenessMax >= 3 ? 5 : 3 }"
          @input="setLivenessMin($event)"
        />
        <input
          type="range" min="0" max="4" step="1"
          :value="livenessMax"
          aria-label="Maximum liveness score"
          class="liveness-range absolute inset-0 w-full"
          style="z-index: 4"
          @input="setLivenessMax($event)"
        />
      </div>
      <div class="mt-0.5 flex justify-between px-1 text-xs text-gray-400 dark:text-gray-500">
        <span v-for="n in 5" :key="n">{{ n - 1 }}</span>
      </div>
      <label v-if="livenessNarrowed" class="tap-target-touch mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input v-model="includeUnchecked" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
        Also show markets that haven't been checked
      </label>
      <p v-if="livenessNarrowed && livenessMin <= 1" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
        Markets scoring 1 or below are marked inactive &mdash; also tick Inactive under Status to see them.
      </p>
    </div>

    <button
      v-if="customFilterCount"
      type="button"
      class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
      @click="emit('clear')"
    >Clear schedule &amp; liveness filters</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

// The state lives in the page (it drives which rows show); this component is
// only the controls, so the Filters dropdown and the toolbar popover can both
// render it and never disagree.
defineProps<{
  frequencies: Record<string, string>
  customFilterCount: number
}>()
const emit = defineEmits<{ clear: [] }>()

const freqStates = defineModel<Record<string, 'include' | 'exclude'>>('freqStates', { required: true })
const months = defineModel<number[]>('months', { required: true })
const matchMode = defineModel<'all' | 'any'>('matchMode', { required: true })
const livenessMin = defineModel<number>('livenessMin', { required: true })
const livenessMax = defineModel<number>('livenessMax', { required: true })
const includeUnchecked = defineModel<boolean>('includeUnchecked', { required: true })

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const includesFrequency = computed(() => Object.values(freqStates.value).includes('include'))
const livenessNarrowed = computed(() => livenessMin.value > 0 || livenessMax.value < 4)

const cycleFrequency = (key: string) => {
  const next = { ...freqStates.value }
  if (!next[key]) next[key] = 'include'
  else if (next[key] === 'include') next[key] = 'exclude'
  else delete next[key]
  freqStates.value = next
}
const toggleMonth = (month: number) => {
  months.value = months.value.includes(month) ? months.value.filter((m) => m !== month) : [...months.value, month]
}
// Thumbs can't cross; the DOM value is reset too, because when the clamp lands
// on the model's old value Vue sees no change and would leave the thumb
// wherever it was dragged.
const setLivenessMin = (event: Event) => {
  const input = event.target as HTMLInputElement
  livenessMin.value = Math.min(input.valueAsNumber, livenessMax.value)
  input.value = String(livenessMin.value)
}
const setLivenessMax = (event: Event) => {
  const input = event.target as HTMLInputElement
  livenessMax.value = Math.max(input.valueAsNumber, livenessMin.value)
  input.value = String(livenessMax.value)
}

const chipBase = 'tap-target-touch inline-flex items-center rounded-full border px-3 py-1 text-sm'
const chipClass = (state: 'include' | 'exclude' | undefined) => {
  if (state === 'include') return 'border-indigo-600 bg-indigo-600 text-white'
  if (state === 'exclude') return 'border-red-500 bg-red-50 text-red-700 line-through dark:bg-red-900/20 dark:text-red-300'
  return 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300'
}
</script>

<style scoped>
/* Two overlaid range inputs make one two-thumb slider: the inputs themselves
   ignore the pointer so the top one doesn't block the other, and only the
   thumbs take clicks. */
.liveness-range {
  pointer-events: none;
  appearance: none;
  -webkit-appearance: none;
  background: transparent;
  height: 1.5rem;
  margin: 0;
}
.liveness-range::-webkit-slider-runnable-track {
  background: transparent;
}
.liveness-range::-moz-range-track {
  background: transparent;
}
.liveness-range::-webkit-slider-thumb {
  pointer-events: auto;
  -webkit-appearance: none;
  appearance: none;
  height: 18px;
  width: 18px;
  border-radius: 9999px;
  background: #4f46e5;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #4f46e5;
  cursor: pointer;
}
.liveness-range::-moz-range-thumb {
  pointer-events: auto;
  height: 14px;
  width: 14px;
  border-radius: 9999px;
  background: #4f46e5;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px #4f46e5;
  cursor: pointer;
}
.liveness-range:focus-visible::-webkit-slider-thumb {
  outline: 2px solid #818cf8;
  outline-offset: 2px;
}
</style>
