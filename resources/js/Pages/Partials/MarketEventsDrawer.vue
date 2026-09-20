<template>
  <!-- Mobile-only bottom drawer, the same pattern as the main app's
       CustomerEventsDrawer: an always-visible footer bar that opens (tap or
       drag up) to this market's history, with the filters pinned on top and
       the list infinite-scrolling underneath. -->
  <div class="md:hidden fixed inset-x-0 bottom-0 z-40 max-h-[100dvh]">
    <div v-if="isOpen" class="fixed inset-0 -z-10 bg-black/30" @click="close" />

    <div class="max-h-[100dvh] overflow-hidden bg-white dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 shadow-[inset_0_1px_0_rgba(255,255,255,0.6),0_-4px_14px_rgba(0,0,0,0.18)] dark:shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_-4px_14px_rgba(0,0,0,0.45)] flex flex-col">
      <button
        type="button"
        class="tap-target-touch relative flex items-center justify-center gap-1.5 pt-4 pb-[18px] text-sm font-medium text-gray-700 dark:text-gray-200"
        @pointerdown="onDragStart"
        @pointermove="onDragMove"
        @pointerup="onDragEnd"
        @pointercancel="onDragEnd"
        @click="onClick"
      >
        <span class="absolute left-1/2 -translate-x-1/2 top-0 pt-1.5 touch-none">
          <span class="block w-9 h-1 rounded-full bg-gray-300 dark:bg-gray-500"></span>
        </span>
        History
        <span
          v-if="feed.hasFilters.value"
          class="inline-flex h-2 w-2 rounded-full bg-indigo-500"
          aria-label="Filters applied"
        ></span>
      </button>

      <div
        class="overflow-y-auto"
        :class="{ 'transition-[height] duration-200 ease-out': !isDragging }"
        :style="{ height: contentHeight + 'px' }"
      >
        <div class="sticky top-0 z-10 border-b border-gray-100 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2">
          <MarketEventFilters :feed="feed" />
        </div>
        <MarketEventList :feed="feed" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useCustomerEventsDrawerGeometry } from '@/composables/useCustomerEventsDrawerGeometry'
import MarketEventFilters from './MarketEventFilters.vue'
import MarketEventList from './MarketEventList.vue'
import type { MarketEventsFeed } from './useMarketEvents'

defineProps<{ feed: MarketEventsFeed }>()

// The geometry composable is named for the customer drawer that introduced it,
// but it's just "how tall is a snap-open bottom sheet on this screen".
const { maxHeight, threeQuarterHeight } = useCustomerEventsDrawerGeometry()

const isOpen = ref(false)
const contentHeight = ref(0)
const isDragging = ref(false)

const open = () => {
  isOpen.value = true
  contentHeight.value = threeQuarterHeight.value
}
const close = () => {
  isOpen.value = false
  contentHeight.value = 0
}
const toggle = () => (isOpen.value ? close() : open())

// Drag tracking, as in CustomerEventsDrawer: a small movement threshold so a
// tap isn't mistaken for a drag, and the click that follows a drag is ignored.
const DRAG_THRESHOLD = 10
let startY = 0
let startHeight = 0
let dragMoved = false

const onDragStart = (event: PointerEvent) => {
  isDragging.value = true
  dragMoved = false
  startY = event.clientY
  startHeight = contentHeight.value
  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

const onDragMove = (event: PointerEvent) => {
  if (!isDragging.value) return
  const delta = startY - event.clientY
  if (Math.abs(delta) > DRAG_THRESHOLD) dragMoved = true
  if (dragMoved) contentHeight.value = Math.min(maxHeight.value, Math.max(0, startHeight + delta))
}

const onDragEnd = () => {
  if (!isDragging.value) return
  isDragging.value = false
  if (!dragMoved) {
    contentHeight.value = startHeight
    return
  }
  const snapPoints = [0, threeQuarterHeight.value, maxHeight.value]
  const nearest = snapPoints.reduce((closest, point) =>
    Math.abs(point - contentHeight.value) < Math.abs(closest - contentHeight.value) ? point : closest,
  )
  isOpen.value = nearest > 0
  contentHeight.value = nearest
}

const onClick = () => {
  if (dragMoved) return
  toggle()
}
</script>
