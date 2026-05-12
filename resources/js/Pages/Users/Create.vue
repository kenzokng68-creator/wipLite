<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ArrowLeft, Save } from 'lucide-vue-next';

const props = defineProps({
  roles: Array,
  employees: Array,
});

const form = useForm({
  email: '',
  password: '',
  role_id: '',
});

const handleSubmit = () => {
  form.post(route('users.store'), {
    onSuccess: () => {
      // Success handled by Inertia
    }
  });
};
</script>

<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-4">
        <Link
          :href="route('users.index')"
          class="p-2 hover:bg-white hover:shadow-md rounded-xl text-slate-600 transition-all"
        >
          <ArrowLeft class="w-5 h-5" />
        </Link>
        <div>
          <h2 class="text-3xl font-bold text-slate-800">Nouvel Utilisateur</h2>
          <p class="text-slate-500 mt-1">Créer un nouveau compte utilisateur</p>
        </div>
      </div>
    </template>

    <div class="max-w-2xl mx-auto">
      <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-lg border border-blue-100/50 p-8">
        <form @submit.prevent="handleSubmit" class="space-y-6">
          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Choisir un employé (Email)</label>
            <select
              v-model="form.email"
              class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white/50 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
            >
              <option value="">Sélectionner un employé</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.email">
                {{ emp.last_name }} {{ emp.first_name }} ({{ emp.email }})
              </option>
            </select>
            <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">{{ form.errors.email }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Mot de passe</label>
            <input
              v-model="form.password"
              type="password"
              class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white/50 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
              placeholder="Mot de passe (min. 4 caractères)"
            />
            <div v-if="form.errors.password" class="text-red-500 text-sm mt-1">{{ form.errors.password }}</div>
          </div>

          <div>
            <label class="block text-sm font-semibold text-slate-700 mb-2">Rôle</label>
            <select
              v-model="form.role_id"
              class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white/50 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
            >
              <option value="">Sélectionner un rôle</option>
              <option v-for="role in roles" :key="role.id" :value="role.id">
                {{ role.name }}
              </option>
            </select>
            <div v-if="form.errors.role_id" class="text-red-500 text-sm mt-1">{{ form.errors.role_id }}</div>
          </div>

          <div class="flex justify-end gap-4 pt-4">
            <Link
              :href="route('users.index')"
              class="px-6 py-3 rounded-xl text-slate-700 font-medium hover:bg-slate-100 transition-all"
            >
              Annuler
            </Link>
            <button
              type="submit"
              :disabled="form.processing"
              class="flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold shadow-lg shadow-blue-500/30 transition-all disabled:opacity-50"
            >
              <Save class="w-5 h-5" />
              <span>Créer</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
