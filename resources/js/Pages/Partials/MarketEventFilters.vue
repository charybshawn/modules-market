<template>
  <div class="flex flex-wrap items-center gap-2">
    <select
      :value="feed.filters.value.kind"
      aria-label="Kind of change"
      :class="selectClass"
      @change="set('kind', $event)"
    >
      <option value="">All changes</option>
      <option v-for="(label, value) in feed.config.kinds" :key="value" :value="value">{{ label }}</option>
    </select>

    <select
      :value="feed.filters.value.source"
      aria-label="Where the change came from"
      :class="selectClass"
      @change="set('source', $event)"
    >
      <option value="">Any source</option>
      <option v-for="(label, value) in feed.config.sources" :key="value" :value="value">{{ label }}</option>
    </select>

    <select
      :value="feed.filters.value.field"
      aria-label="Field that changed"
      :class="selectClass"
      @change="set('field', $event)"
    >
      <option value="">Any field</option>
      <option v-for="(label, value) in feed.config.fields" :key="value" :value="value">{{ label }}</option>
    </select>

    <button
      v-if="feed.hasFilters.value"
      type="button"
      class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
      @click="feed.clearFilters()"
    >Clear</button>
  </div>
</template>

<script setup lang="ts">
import type { HistoryFilters, MarketEventsFeed } from './useMarketEvents'

const props = defineProps<{ feed: MarketEventsFeed }>()

const selectClass =
  'rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm py-1.5'

const set = (key: keyof HistoryFilters, event: Event) => {
  props.feed.filters.value = { ...props.feed.filters.value, [key]: (event.target as HTMLSelectElement).value }
}
</script>
