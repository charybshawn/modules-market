<template>
  <div>
    <div v-if="!feed.loaded.value" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading…</div>

    <div v-else-if="feed.failed.value" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      Couldn't load the history.
      <button type="button" class="ml-1 font-medium text-indigo-600 dark:text-indigo-400" @click="feed.reload()">Try again</button>
    </div>

    <div v-else-if="feed.events.value.length === 0" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
      {{ feed.hasFilters.value ? 'Nothing matches these filters.' : 'No history recorded yet.' }}
    </div>

    <ul v-else class="divide-y divide-gray-100 dark:divide-gray-600" :class="{ 'opacity-60': feed.loading.value }">
      <li v-for="event in feed.events.value" :key="event.id" class="px-4 py-3 flex items-start gap-3">
        <span :class="['w-2 h-2 mt-1.5 rounded-full shrink-0', dot(event)]"></span>
        <div class="min-w-0 flex-1 space-y-0.5">
          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ title(event) }}</p>
          <p v-for="change in event.changes" :key="change.field" class="text-sm text-gray-700 dark:text-gray-300 break-words">
            <span class="text-gray-500 dark:text-gray-400">{{ change.field }}:</span>
            {{ change.old }} <span class="text-gray-400">→</span> {{ change.new }}
          </p>
          <p v-for="note in event.schedule_notes" :key="note" class="text-sm text-gray-700 dark:text-gray-300 break-words">{{ note }}</p>
          <p class="text-xs text-gray-400 dark:text-gray-500" :title="event.created_at_label">
            {{ event.actor ?? 'Unknown user' }} · {{ event.source }} · {{ event.created_at_human }}
          </p>
        </div>
      </li>

      <li v-if="feed.hasMore.value || feed.loadingMore.value" class="py-4">
        <div
          :ref="(el) => observeSentinel('sentinel', el as Element | null)"
          class="flex items-center justify-center gap-2 min-h-4 text-sm text-gray-500 dark:text-gray-400"
        >
          <svg v-if="feed.loadingMore.value" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span v-if="feed.loadingMore.value">Loading more…</span>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { useInfiniteScrollSentinel } from '@/composables/useInfiniteScrollSentinel'
import type { MarketEvent, MarketEventsFeed } from './useMarketEvents'

const props = defineProps<{ feed: MarketEventsFeed }>()

const { observe: observeSentinel } = useInfiniteScrollSentinel(() => props.feed.loadMore())

const dot = (event: MarketEvent) =>
  event.kind === 'created' ? 'bg-emerald-400' : event.severity === 'warning' ? 'bg-yellow-400' : 'bg-blue-400'

const title = (event: MarketEvent) => {
  if (event.kind === 'created') return 'Created'
  const fields = [...event.changes.map((c) => c.field), ...(event.schedule_notes.length ? ['Schedules'] : [])]
  return fields.length ? `Edited · ${fields.join(', ')}` : 'Edited'
}
</script>
