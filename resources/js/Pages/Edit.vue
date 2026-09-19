<template>
  <div class="md:pt-6 pb-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <AdminMobileHeader title="Edit Market" :href="route('admin.market.index')" />

      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="hidden md:flex p-6 border-b border-gray-200 dark:border-gray-700 justify-between items-center">
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit Market</h1>
          <Link :href="route('admin.market.index')" class="tap-target-touch inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-8">
          <FormErrorSummary :errors="form.errors" />

          <div class="space-y-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Basics</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
              <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City</label>
                <input v-model="form.city" type="text" list="city-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Market Type</label>
                <input v-model="form.market_type" type="text" list="market-type-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
                <datalist id="market-type-options">
                  <option v-for="t in props.marketTypes" :key="t" :value="t" />
                </datalist>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                <input v-model="form.address" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
            </div>

            <label class="tap-target-touch flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
              <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
              Active
            </label>
          </div>

          <div class="space-y-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Schedule</h2>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Frequency</label>
              <select v-model="form.frequency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                <option :value="null">—</option>
                <option v-for="(label, value) in props.frequencies" :key="value" :value="value">{{ label }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Frequency Detail</label>
              <textarea v-model="form.frequency_detail" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"></textarea>
            </div>
          </div>

          <div class="space-y-6 pt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contact</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                <input v-model="form.phone" type="tel" inputmode="tel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manager</label>
                <input v-model="form.manager" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
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
                <input v-model="form.facebook_page" type="url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Instagram Page</label>
                <input v-model="form.instagram_page" type="url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
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
              <textarea v-model="form.vendor_fees" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
              <textarea v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
              <textarea v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sources</label>
              <textarea v-model="form.sources" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"></textarea>
            </div>
          </div>

          <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="destroy" class="tap-target-touch px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete Market</button>
            <div class="flex items-center">
              <Link :href="route('admin.market.index')" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
              <button type="submit" :disabled="form.processing" class="tap-target-touch ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                <span v-if="form.processing">Saving...</span>
                <span v-else>Save</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface MarketDetail {
  id: number
  name: string
  city: string | null
  region: string | null
  market_type: string | null
  address: string | null
  frequency: string | null
  frequency_detail: string | null
  vendor_fees: string | null
  phone: string | null
  manager: string | null
  manager_email: string | null
  facebook_page: string | null
  instagram_page: string | null
  website: string | null
  description: string | null
  notes: string | null
  sources: string | null
  is_active: boolean
}

interface Props {
  market: MarketDetail
  cities: string[]
  regions: string[]
  marketTypes: string[]
  frequencies: Record<string, string>
}

const props = defineProps<Props>()

const form = useForm({
  name: props.market.name,
  city: props.market.city ?? '',
  region: props.market.region,
  market_type: props.market.market_type ?? '',
  address: props.market.address ?? '',
  frequency: props.market.frequency,
  frequency_detail: props.market.frequency_detail ?? '',
  vendor_fees: props.market.vendor_fees ?? '',
  phone: props.market.phone ?? '',
  manager: props.market.manager ?? '',
  manager_email: props.market.manager_email ?? '',
  facebook_page: props.market.facebook_page ?? '',
  instagram_page: props.market.instagram_page ?? '',
  website: props.market.website ?? '',
  description: props.market.description ?? '',
  notes: props.market.notes ?? '',
  sources: props.market.sources ?? '',
  is_active: props.market.is_active,
})

const submit = () => {
  form.put(route('admin.market.update', props.market.id))
}

const destroy = () => {
  if (confirm(`Delete "${props.market.name}"?`)) {
    router.delete(route('admin.market.destroy', props.market.id))
  }
}
</script>
