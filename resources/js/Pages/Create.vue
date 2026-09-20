<template>
  <div class="md:pt-6 pb-6">
    <!-- No base px-4 here -- AdminLayout's own <main> wrapper already sits
         at px-0 on mobile (sm:px-6 lg:px-8 only from sm up) precisely so
         pages don't get a second, redundant side margin stacked on top of
         it; a page-local px-4 here would just reintroduce the margin the
         layout deliberately avoids. AdminMobileHeader and the card below
         both go full-bleed on mobile as a result, matching Index.vue
         (which never had this wrapper at all) -- their own internal
         padding (AdminMobileHeader's px-4, the form's p-6) is what
         provides breathing room, not an outer margin. -->
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
      <AdminMobileHeader title="Add Market" :href="route('admin.market.index')" />

      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="hidden md:flex p-6 border-b border-gray-200 dark:border-gray-700 justify-between items-center">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Add Market</h1>
          <Link :href="route('admin.market.index')" class="tap-target-touch inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-8">
          <FormErrorSummary :errors="form.errors" />

          <div class="space-y-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Basics</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
              <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. Salmon Arm Farmers Market" />
              <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                <input v-model="form.city" type="text" list="city-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. Salmon Arm" />
                <datalist id="city-options">
                  <option v-for="c in props.cities" :key="c" :value="c" />
                </datalist>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                <select v-model="form.region" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                  <option :value="null">—</option>
                  <option v-for="r in props.regions" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Market Type</label>
              <input v-model="form.market_type" type="text" list="market-type-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. Farmers, Artisan, Makers" />
              <datalist id="market-type-options">
                <option v-for="t in props.marketTypes" :key="t" :value="t" />
              </datalist>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sponsor</label>
              <input v-model="form.sponsor" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The business or organization behind the market, when it isn't in the market's name.</p>
            </div>

            <label class="tap-target-touch flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
              <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
              Active
            </label>
          </div>

          <!-- Split rather than one free-text line so this can eventually be
               filled by Canada Post's AddressComplete widget (it returns
               exactly this shape: street line, optional unit/suite line,
               province, postal code) -- City above already covers the city
               part and doubles as a filter/index field, so isn't repeated
               here. -->
          <div class="space-y-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Street Address</label>
              <input v-model="form.address_line1" type="text" autocomplete="address-line1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. 100 Ross St" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Unit / Suite</label>
              <input v-model="form.address_line2" type="text" autocomplete="address-line2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province</label>
                <input v-model="form.province" type="text" autocomplete="address-level1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Postal Code</label>
                <input v-model="form.postal_code" type="text" autocomplete="postal-code" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="V1E 4N2" />
              </div>
            </div>
          </div>

          <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
            <ScheduleFields v-model="form.schedules" :frequencies="props.frequencies" :liveness-labels="props.livenessLabels" :errors="form.errors" />
          </div>

          <div class="space-y-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Market Phone</label>
              <input v-model="form.phone" type="tel" inputmode="tel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="General/public line" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manager</label>
                <input v-model="form.manager" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manager Phone</label>
                <input v-model="form.manager_phone" type="tel" inputmode="tel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="Direct line, if different" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manager Email</label>
              <input v-model="form.manager_email" type="email" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              <p v-if="form.errors.manager_email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.manager_email }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Facebook Page</label>
                <input v-model="form.facebook_page" type="url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="https://facebook.com/..." />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Instagram Page</label>
                <input v-model="form.instagram_page" type="url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="https://instagram.com/..." />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Website</label>
              <input v-model="form.website" type="url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
            </div>
          </div>

          <div class="space-y-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Admin</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vendor Fees</label>
              <textarea v-model="form.vendor_fees" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. $25/weekend, $400/season"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
              <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="Public-facing blurb -- what is this market?"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
              <textarea v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="Internal notes"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sources</label>
              <textarea v-model="form.sources" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="Where this data came from -- one URL/note per line"></textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Liveness Score</label>
                <select v-model="form.liveness_score" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                  <option :value="null">Not checked</option>
                  <option v-for="(label, score) in props.livenessLabels" :key="score" :value="Number(score)">{{ score }}/4 &middot; {{ label }}</option>
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How confident a check is that this market still actually runs -- see the find-bc-markets skill for how this gets scored. A score of 1 or below marks the market inactive when saved.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Checked On</label>
                <input v-model="form.liveness_checked_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('admin.market.index')" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="tap-target-touch ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="form.processing">Creating...</span>
              <span v-else>Create Market</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import ScheduleFields, { type ScheduleForm } from './Partials/ScheduleFields.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Props {
  cities: string[]
  regions: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
  livenessLabels: Record<number, string>
}

const props = defineProps<Props>()

interface FormData {
  name: string
  city: string
  region: string | null
  market_type: string
  sponsor: string
  address_line1: string
  address_line2: string
  province: string
  postal_code: string
  vendor_fees: string
  phone: string
  manager: string
  manager_phone: string
  manager_email: string
  facebook_page: string
  instagram_page: string
  website: string
  description: string
  notes: string
  sources: string
  liveness_score: number | null
  liveness_checked_at: string
  is_active: boolean
  schedules: ScheduleForm[]
}

const form = useForm<FormData>({
  name: '',
  city: '',
  region: null,
  market_type: '',
  sponsor: '',
  address_line1: '',
  address_line2: '',
  // Overwhelming default for this BC-only directory -- not enforced,
  // just saves re-typing it on every single market.
  province: 'BC',
  postal_code: '',
  vendor_fees: '',
  phone: '',
  manager: '',
  manager_phone: '',
  manager_email: '',
  facebook_page: '',
  instagram_page: '',
  website: '',
  description: '',
  notes: '',
  sources: '',
  liveness_score: null,
  liveness_checked_at: '',
  is_active: true,
  schedules: [],
})

const submit = () => {
  form.post(route('admin.market.store'))
}
</script>
