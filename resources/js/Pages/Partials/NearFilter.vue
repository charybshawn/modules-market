<template>
  <FilterSection title="Near" :summary="near ? `${near} · ${minutesLabel(radius)}` : ''">
    <div class="flex flex-wrap items-center gap-2">
      <input
        v-model="text"
        type="text"
        list="market-near-towns"
        placeholder="Town or postal code"
        autocomplete="off"
        aria-label="Town or postal code to search near"
        :class="[field, 'w-56']"
      />
      <datalist id="market-near-towns">
        <option v-for="town in towns" :key="town" :value="town"></option>
      </datalist>
      <template v-if="near">
        <span class="text-sm text-gray-700 dark:text-gray-300">within</span>
        <select v-model.number="radius" aria-label="Drive time" :class="field">
          <option v-for="minutes in RADII" :key="minutes" :value="minutes">{{ minutesLabel(minutes) }}</option>
        </select>
        <span class="text-sm text-gray-700 dark:text-gray-300">drive</span>
        <button type="button" class="text-sm font-medium text-indigo-600 dark:text-indigo-400" @click="text = ''">Clear</button>
      </template>
    </div>
    <p v-if="text && !near" class="mt-1.5 text-xs text-amber-700 dark:text-amber-300">Pick a town from the list, or enter a full postal code like V1E 4N2.</p>
    <p v-else class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
      By road, from the middle of the town or the postal code. Markets with no city, or in a town not in the list, can't be included.
    </p>
    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
      Drive times by <a href="https://openrouteservice.org" target="_blank" rel="noopener" class="underline">openrouteservice.org</a>,
      &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap contributors</a>.
    </p>
  </FilterSection>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import FilterSection from '@/Components/Admin/FilterSection.vue'

const props = defineProps<{ towns: string[] }>()
const near = defineModel<string>('near', { default: '' })
const radius = defineModel<number>('radius', { default: 60 })

// Minutes of driving.
const RADII = [15, 30, 45, 60, 90, 120, 180, 240]
const field = 'text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500'

const minutesLabel = (minutes: number) => {
  if (minutes < 60) return `${minutes} min`
  const rest = minutes % 60
  return rest === 0 ? `${minutes / 60} h` : `${Math.floor(minutes / 60)} h ${rest} min`
}

// The box takes any text; the filter only changes once it names a listed town
// (matched without regard to case) or is a full postal code, so half-typed
// text never reloads the list. A postal code is normalized to "V1E 4N2".
const text = ref(near.value)
const town = (value: string) => props.towns.find((t) => t.toLowerCase() === value.trim().toLowerCase()) ?? ''
const postalCode = (value: string) => {
  const m = value.match(/^\s*([A-Za-z]\d[A-Za-z])[\s-]*(\d[A-Za-z]\d)\s*$/)
  return m ? `${m[1]} ${m[2]}`.toUpperCase() : ''
}
const resolve = (value: string) => town(value) || postalCode(value)

watch(text, (value) => {
  near.value = resolve(value)
})
watch(near, (value) => {
  if (value !== resolve(text.value)) text.value = value
})
</script>
