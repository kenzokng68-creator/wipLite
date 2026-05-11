<script setup>
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import { Clock, User, Calendar, History, ArrowRight, Megaphone } from "lucide-vue-next";

const props = defineProps({
    histories: Object,
});

const getActionSeverity = (type) => {
    switch (type) {
        case 'assign': return 'success';
        case 'transfer': return 'info';
        case 'release': return 'danger';
        default: return 'secondary';
    }
};

const getActionLabel = (type) => {
    switch (type) {
        case 'assign': return 'Affectation';
        case 'transfer': return 'Transfert';
        case 'release': return 'Libération';
        default: return type;
    }
};
</script>

<template>
    <Head title="Historique des Affectations" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Historique des Affectations</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Suivez les mouvements des ressources entre les campagnes</p>
            </div>
        </div>

        <div class="py-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">

                <DataTable
                    :value="histories.data"
                    paginator :rows="15"
                    stripedRows
                    responsiveLayout="stack"
                    class="p-datatable-sm custom-table"
                >
                    <Column header="Ressource">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xs">
                                    {{ data.employee.first_name.charAt(0) }}{{ data.employee.last_name.charAt(0) }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-700 text-sm">{{ data.employee.first_name }} {{ data.employee.last_name }}</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black">{{ data.employee.matricule }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Action">
                        <template #body="{ data }">
                            <Tag :severity="getActionSeverity(data.action_type)" :value="getActionLabel(data.action_type).toUpperCase()" class="!text-[9px] !font-black !px-3" />
                        </template>
                    </Column>

                    <Column header="Mouvement">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-xs">
                                <div v-if="data.old_campaign" class="flex items-center gap-1 text-slate-400">
                                    <Megaphone class="w-3 h-3" />
                                    {{ data.old_campaign.name }}
                                </div>
                                <ArrowRight v-if="data.old_campaign && data.new_campaign" class="w-3 h-3 text-slate-300" />
                                <div v-if="data.new_campaign" class="flex items-center gap-1 text-blue-600 font-bold">
                                    <Megaphone class="w-3 h-3" />
                                    {{ data.new_campaign.name }}
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Manager">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span v-if="data.new_manager">{{ data.new_manager.first_name }} {{ data.new_manager.last_name }}</span>
                                <span v-else-if="data.old_manager" class="line-through">{{ data.old_manager.first_name }} {{ data.old_manager.last_name }}</span>
                                <span v-else>—</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="author.name" header="Par">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-slate-600">
                                <User class="w-3.5 h-3.5" />
                                <span class="text-xs font-medium">{{ data.author.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="created_at" header="Date">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <Calendar class="w-3.5 h-3.5" />
                                {{ new Date(data.created_at).toLocaleString() }}
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-20 text-center flex flex-col items-center">
                            <History class="w-12 h-12 text-slate-200 mb-4" />
                            <p class="text-slate-400 font-medium">Aucun historique d'affectation trouvé.</p>
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
