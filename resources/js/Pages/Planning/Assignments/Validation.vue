<script setup>
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Tag from "primevue/tag";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import { UserPlus, Clock, Calendar, CheckCircle, AlertCircle } from "lucide-vue-next";

const props = defineProps({
    pendingAssignments: Array,
});

const selectedAssignments = ref([]);

const getStatusLabel = (status) => {
    switch (status) {
        case 'validé': return 'success';
        case 'en attente': return 'warning';
        case 'suspendu': return 'danger';
        case 'terminé': return 'info';
        default: return 'secondary';
    }
};

const validateAssignment = (id) => {
    router.post(route('planning.assignments.validate', id));
};

const bulkValidate = () => {
    const ids = selectedAssignments.value.map(a => a.id);
    router.post(route('planning.assignments.bulk-validate'), { ids });
    selectedAssignments.value = [];
};

const validateAll = () => {
    router.post(route('planning.assignments.validate-all'));
};
</script>

<template>
    <Head title="Validation des Plannings" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Validation</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Validez les plannings en attente pour les rendre effectifs</p>
            </div>

            <div class="flex gap-3">
                <Button
                    v-if="pendingAssignments.length > 0"
                    @click="validateAll"
                    class="flex-shrink-0 !bg-blue-600 !border-none !rounded-2xl !px-8 !py-4 flex items-center gap-3 shadow-xl shadow-blue-500/20 hover:!bg-blue-700 hover:-translate-y-0.5 transition-all"
                >
                    <CheckCircle class="w-5 h-5 text-white" />
                    <span class="font-black text-white text-sm uppercase tracking-wider">Valider tout ({{ pendingAssignments.length }})</span>
                </Button>
                <Button
                    v-if="selectedAssignments.length > 0"
                    @click="bulkValidate"
                    class="flex-shrink-0 !bg-green-600 !border-none !rounded-2xl !px-8 !py-4 flex items-center gap-3 shadow-xl shadow-green-500/20 hover:!bg-green-700 hover:-translate-y-0.5 transition-all"
                >
                    <CheckCircle class="w-5 h-5 text-white" />
                    <span class="font-black text-white text-sm uppercase tracking-wider">Valider sélection ({{ selectedAssignments.length }})</span>
                </Button>
            </div>
        </div>

        <div class="py-8">
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6">

                <DataTable
                    :value="pendingAssignments"
                    :selection="selectedAssignments"
                    selection-mode="multiple"
                    data-key="id"
                    paginator :rows="10"
                    stripedRows
                    responsiveLayout="stack"
                    class="p-datatable-sm custom-table"
                >
                    <Column selection-mode="multiple" headerStyle="width: 3rem"></Column>

                    <Column header="Collaborateur">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-xs">
                                    {{ data.employee.name.substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-700 text-sm">{{ data.employee.name }}</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black">{{ data.employee.role }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <Column header="Planning">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2 text-blue-600 font-semibold italic">
                                <Clock class="w-3.5 h-3.5" />
                                {{ data.model.name }}
                            </div>
                        </template>
                    </Column>

                    <Column header="Période">
                        <template #body="{ data }">
                            <div class="flex flex-col text-xs font-medium text-slate-500">
                                <span class="flex items-center gap-1">
                                    <Calendar class="w-3 h-3 text-slate-300" />
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

                    <Column header="Actions" headerStyle="width: 20rem; text-align: center">
                        <template #body="{ data }">
                            <div class="flex gap-1.5 justify-center">
                                <Button
                                    @click="validateAssignment(data.id)"
                                    class="!bg-green-600 !border-none !text-white !px-2.5 !py-1.5 !text-[10px] !font-bold !rounded-lg flex items-center gap-1"
                                >
                                    <CheckCircle class="w-3 h-3" />
                                    Valider
                                </Button>
                            </div>
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-20 text-center flex flex-col items-center">
                            <CheckCircle class="w-12 h-12 text-green-100 mb-4" />
                            <p class="text-slate-400 font-medium">Aucun planning en attente de validation.</p>
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
