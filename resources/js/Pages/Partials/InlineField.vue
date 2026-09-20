<template>
  <div>
    <!-- Read state: a small, always-visible (not hover-only, so it works on
         touch) pencil button next to the value, rather than making the whole
         row the click target -- several fields render their value as a real
         link (tel:/mailto:/website), and a row-wide click target would fight
         with actually following that link. -->
    <div v-if="!editing" class="flex items-start gap-1.5">
      <div class="min-w-0 flex-1 break-words" :class="displayValue ? valueClass : `${emptyValueClass} italic`">
        <template v-if="linkifyLines && displayValue">
          <div v-for="(line, i) in displayValue.split('\n')" :key="i">
            <a v-if="isHttpUrl(line)" :href="line" target="_blank" rel="noopener noreferrer" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ line }}</a>
            <template v-else>{{ line }}</template>
          </div>
        </template>
        <a
          v-else-if="href && displayValue"
          :href="href"
          :target="external ? '_blank' : undefined"
          rel="noopener noreferrer"
          class="text-indigo-600 dark:text-indigo-400 hover:underline"
        >{{ displayValue }}</a>
        <span v-else :class="{ 'whitespace-pre-line': multiline }">{{ displayValue ?? placeholder }}</span>
      </div>
      <button
        type="button"
        class="tap-target-touch shrink-0 text-gray-300 hover:text-indigo-600 dark:text-gray-600 dark:hover:text-indigo-400"
        :aria-label="`Edit ${label}`"
        @click="startEdit"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      </button>
    </div>

    <!-- Edit state -->
    <div v-else class="space-y-1.5">
      <select
        v-if="type === 'select'"
        ref="inputEl"
        v-model="draft"
        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
        :disabled="saving"
        @keydown.esc="cancel"
      >
        <option :value="null">—</option>
        <option v-for="option in options" :key="String(option.value)" :value="option.value">{{ option.label }}</option>
      </select>

      <textarea
        v-else-if="type === 'textarea'"
        ref="inputEl"
        v-model="draft"
        rows="3"
        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
        :disabled="saving"
        @keydown.esc="cancel"
      ></textarea>

      <template v-else>
        <input
          ref="inputEl"
          v-model="draft"
          :type="type"
          :list="datalistOptions ? datalistId : undefined"
          class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
          :disabled="saving"
          @keydown.enter.prevent="submit"
          @keydown.esc="cancel"
        />
        <datalist v-if="datalistOptions" :id="datalistId">
          <option v-for="value in datalistOptions" :key="value" :value="value" />
        </datalist>
      </template>

      <p v-if="error" class="text-xs text-red-600 dark:text-red-400">{{ error }}</p>

      <div class="flex items-center gap-2">
        <button
          type="button"
          class="tap-target-touch rounded bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="saving"
          @click="submit"
        >{{ saving ? 'Saving…' : 'Save' }}</button>
        <button
          type="button"
          class="tap-target-touch rounded px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50"
          :disabled="saving"
          @click="cancel"
        >Cancel</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'

type FieldValue = string | number | null

interface Props {
  label: string
  modelValue: FieldValue
  displayValue?: string | null
  type?: 'text' | 'textarea' | 'tel' | 'email' | 'url' | 'date' | 'select'
  options?: { value: FieldValue; label: string }[]
  datalistOptions?: string[]
  placeholder?: string
  href?: string | null
  external?: boolean
  multiline?: boolean
  /** Read mode only: each line of the value that looks like an http(s) URL
   * becomes its own link (the Sources field's existing behavior) -- edit
   * mode is still one plain textarea. Implies multiline. */
  linkifyLines?: boolean
  /** Read-mode text styling, e.g. for the name field rendering at heading size. */
  valueClass?: string
  /** Returns a promise that rejects with an Error(message) on failure. */
  onSave: (value: FieldValue) => Promise<void>
}

const props = withDefaults(defineProps<Props>(), {
  displayValue: undefined,
  type: 'text',
  options: undefined,
  datalistOptions: undefined,
  placeholder: '—',
  href: null,
  external: false,
  multiline: false,
  linkifyLines: false,
  valueClass: 'text-sm text-gray-900 dark:text-white',
})

// Same rule as the module's other free-text URL fields: only a real http(s)
// value becomes a link, never e.g. a "javascript:" string typed into a
// free-text field.
const isHttpUrl = (value: string) => /^https?:\/\//i.test(value)

// A caller-supplied valueClass is only ever used for a value that's actually
// there (e.g. the name field's heading size) -- the empty/placeholder state
// always uses the same small muted text regardless, since "—" or a
// placeholder doesn't need to be heading-sized.
const emptyValueClass = 'text-sm text-gray-400 dark:text-gray-500'

// displayValue lets a caller show something derived (e.g. a formatted date)
// while still editing the raw underlying value. A computed, not a plain
// const -- modelValue comes from a live Inertia prop (market.phone etc.)
// that changes after every successful save, and the read-mode view has to
// track it or it'd keep showing the pre-edit value.
const displayValue = computed(() =>
  props.displayValue !== undefined ? props.displayValue : (props.modelValue !== null ? String(props.modelValue) : null),
)

const datalistId = `inline-field-${Math.random().toString(36).slice(2)}`

const editing = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const draft = ref<FieldValue>(props.modelValue)
const inputEl = ref<HTMLElement | null>(null)

const startEdit = async () => {
  draft.value = props.modelValue
  error.value = null
  editing.value = true
  await nextTick()
  const el = inputEl.value as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null
  el?.focus()
  if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) el.select()
}

const cancel = () => {
  editing.value = false
  error.value = null
}

const submit = async () => {
  error.value = null
  // A blank text field means "clear it", same as leaving it blank on the
  // full Edit form -- not a value worth sending as the literal empty string.
  const value = typeof draft.value === 'string' && draft.value.trim() === '' ? null : draft.value
  saving.value = true
  try {
    await props.onSave(value)
    editing.value = false
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Could not save.'
  } finally {
    saving.value = false
  }
}
</script>
