import { computed, onMounted, ref, watch } from 'vue'

export interface MarketEvent {
  id: number
  kind: 'created' | 'updated'
  severity: string
  actor: string | null
  source: string
  created_at: string
  created_at_human: string
  created_at_label: string
  changes: { field: string; old: string; new: string }[]
  schedule_notes: string[]
}

export interface HistoryConfig {
  enabled: boolean
  url: string
  kinds: Record<string, string>
  sources: Record<string, string>
  fields: Record<string, string>
}

export interface HistoryFilters {
  kind: string
  source: string
  field: string
}

/**
 * One market's history feed: first page on mount, further pages on demand
 * (infinite scroll), refetched from page 1 whenever a filter changes. Called
 * once by the market page and handed to both the desktop section and the
 * mobile drawer, so the two never fetch separately or disagree.
 */
export function useMarketEvents(config: HistoryConfig) {
  const events = ref<MarketEvent[]>([])
  const hasMore = ref(false)
  const loading = ref(false)
  const loadingMore = ref(false)
  const loaded = ref(false)
  const failed = ref(false)
  const filters = ref<HistoryFilters>({ kind: '', source: '', field: '' })

  let page = 1
  // A response for filters that have since changed must not overwrite the list.
  let generation = 0

  const hasFilters = computed(() => Object.values(filters.value).some(Boolean))

  const urlFor = (p: number) => {
    const params = new URLSearchParams({ page: String(p) })
    for (const [key, value] of Object.entries(filters.value)) {
      if (value) params.set(key, value)
    }
    return `${config.url}?${params}`
  }

  const fetchPage = async (p: number) => {
    const response = await fetch(urlFor(p), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    if (!response.ok) throw new Error(`History request failed (${response.status})`)
    return response.json()
  }

  const reload = async () => {
    const mine = ++generation
    page = 1
    loading.value = true
    failed.value = false
    try {
      const data = await fetchPage(1)
      if (mine !== generation) return
      events.value = data.data ?? []
      hasMore.value = data.has_more ?? false
    } catch {
      if (mine !== generation) return
      events.value = []
      hasMore.value = false
      failed.value = true
    } finally {
      if (mine === generation) {
        loading.value = false
        loaded.value = true
      }
    }
  }

  const loadMore = async () => {
    if (loading.value || loadingMore.value || !hasMore.value) return
    const mine = generation
    loadingMore.value = true
    try {
      const data = await fetchPage(page + 1)
      if (mine !== generation) return
      events.value = [...events.value, ...(data.data ?? [])]
      hasMore.value = data.has_more ?? false
      page += 1
    } catch {
      // Leave what's loaded in place; the next scroll into the sentinel retries.
    } finally {
      loadingMore.value = false
    }
  }

  const clearFilters = () => {
    filters.value = { kind: '', source: '', field: '' }
  }

  if (config.enabled) {
    onMounted(reload)
    watch(filters, reload, { deep: true })
  }

  return { config, events, hasMore, loading, loadingMore, loaded, failed, filters, hasFilters, reload, loadMore, clearFilters }
}

export type MarketEventsFeed = ReturnType<typeof useMarketEvents>
