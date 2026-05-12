<script setup>
/**
 * Vue "Planning de l'Équipe"
 * Réservée aux Superviseurs pour suivre les plannings des agents qu'ils encadrent.
 */
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Tag from "primevue/tag";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import { Clock, Calendar, Users, AlertCircle } from "lucide-vue-next";

// Propriétés reçues du contrôleur
const props = defineProps({
    assignments: Array, // Liste des plannings des agents de l'équipe
});

const getStatusLabel = (status) => {
    switch (status) {
        case 'validé': return 'success';
        case 'en attente': return 'warning';
        case 'suspendu': return 'danger';
        case 'terminé': return 'info';
        default: return 'secondary';
    }
};
</script>

<template>
    <Head title="Planning de l'Équipe" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Planning de l'Équipe</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Consultez les plannings des agents sous votre supervision</p>
            </div>
        </div>

        <div class="py-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">
                <DataTable
                    :value="assignments"
                    paginator :rows="10"
                    stripedRows
                    responsiveLayout="stack"
                    class="p-datatable-sm custom-table"
                >
                    <template #header>
                        <div class="flex items-center gap-2 mb-2">
                            <Users class="w-5 h-5 text-blue-600" />
                            <span class="font-bold text-slate-700">Agents affectés</span>
                        </div>
                    </template>

                    <Column field="employee.name" header="Agent">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xs">
                                    {{ data.employee.name.substring(0, 2).toUpperCase() }}
                                </div>
                                <span class="font-bold text-slate-700 text-sm">{{ data.employee.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Planning">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2" :class="data.has_planning ? 'text-blue-600 font-semibold' : 'text-slate-400'">
                                <Clock class="w-3.5 h-3.5" />
                                {{ data.model.name }}
                            </div>
                        </template>
                    </Column>

                    <Column header="Période">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs font-medium" :class="data.has_planning ? 'text-slate-500' : 'text-slate-300'">
                                <span class="flex items-center gap-1">
                                    <Calendar class="w-3 h-3" />
                                    Du {{ data.start_date }} au {{ data.end_date }}
                                </span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Statut">
                        <template #body="{ data }">
                            <Tag :severity="getStatusLabel(data.status)" :value="data.status.toUpperCase()" class="!text-[9px] !font-black !px-3" />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-20 text-center flex flex-col items-center">
                            <AlertCircle class="w-12 h-12 text-slate-100 mb-4" />
                            <p class="text-slate-400 font-medium">Aucun planning trouvé pour votre équipe.</p>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-table :deep(.p-datatable-thead > tr > th) {
    @apply !bg-transparent !text-slate-400 !text-[11px] !font-black !uppercase !tracking-widest !border-b !border-slate-50;
}
.custom-table :deep(.p-datatable-tbody > tr) {
    @apply !transition-colors;
}
.custom-table :deep(.p-datatable-tbody > tr:hover) {
    @apply !bg-blue-50/30;
}
</style>
