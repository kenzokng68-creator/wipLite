<script setup>
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Button from "primevue/button";
import Tag from "primevue/tag";
import Dialog from "primevue/dialog";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Select from "primevue/select";
import DatePicker from "primevue/datepicker";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";
import { UserPlus, Clock, Eye, Calendar, CheckCircle, AlertCircle, PauseCircle, XCircle, Megaphone } from "lucide-vue-next";

const props = defineProps({
    supervisorAssignments: Array,
    campaigns: Array,
    chefsDePlateau: Array,
    supPositionId: Number,
});

const toast = useToast();
const confirm = useConfirm();
const selectedSupervisor = ref(null);
const showTeleconseillersModal = ref(false);

const getStatusLabel = (status) => {
    switch (status) {
        case 'validé': return 'success';
        case 'en attente': return 'warn';
        case 'suspendu': return 'danger';
        case 'terminé': return 'secondary';
        case 'non assigné': return 'info';
        default: return 'secondary';
    }
};

const viewTeleconseillers = (supervisorData) => {
    selectedSupervisor.value = supervisorData;
    showTeleconseillersModal.value = true;
};

const validateAssignment = (id) => {
    confirm.require({
        message: 'Voulez-vous valider cette affectation de planning ?',
        header: 'Validation',
        icon: 'pi pi-check-circle',
        acceptProps: { label: 'Valider', severity: 'success' },
        rejectProps: { label: 'Annuler', severity: 'secondary', variant: 'text' },
        accept: () => {
            router.post(route('planning.assignments.validate', id), {}, {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Validé', detail: 'Le planning a été validé.', life: 3000 });
                }
            });
        }
    });
};

const validateAll = () => {
    confirm.require({
        message: 'Voulez-vous valider TOUTES les affectations en attente ?',
        header: 'Validation Globale',
        icon: 'pi pi-check-circle',
        acceptProps: { label: 'Tout Valider', severity: 'success' },
        rejectProps: { label: 'Annuler', severity: 'secondary', variant: 'text' },
        accept: () => {
            router.post(route('planning.assignments.validateAll'), {}, {
                onSuccess: () => {
                    toast.add({ severity: 'success', summary: 'Validé', detail: 'Toutes les affectations en attente ont été validées.', life: 3000 });
                }
            });
        }
    });
};

const suspendAssignment = (id) => {
    confirm.require({
        message: 'Voulez-vous suspendre cette affectation de planning ?',
        header: 'Suspension',
        icon: 'pi pi-pause-circle',
        acceptProps: { label: 'Suspendre', severity: 'warn' },
        rejectProps: { label: 'Annuler', severity: 'secondary', variant: 'text' },
        accept: () => {
            router.post(route('planning.assignments.suspend', id), {}, {
                onSuccess: () => {
                    toast.add({ severity: 'warn', summary: 'Suspendu', detail: 'Le planning a été suspendu.', life: 3000 });
                }
            });
        }
    });
};

const terminateAssignment = (id) => {
    confirm.require({
        message: 'Voulez-vous terminer définitivement cette affectation ?',
        header: 'Terminaison',
        icon: 'pi pi-times-circle',
        acceptProps: { label: 'Terminer', severity: 'danger' },
        rejectProps: { label: 'Annuler', severity: 'secondary', variant: 'text' },
        accept: () => {
            router.post(route('planning.assignments.terminate', id), {}, {
                onSuccess: () => {
                    toast.add({ severity: 'info', summary: 'Terminé', detail: 'L\'affectation a été clôturée.', life: 3000 });
                }
            });
        }
    });
};
</script>

<template>
    <Head title="Affectations des Plannings" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Affectations</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Gérez les plannings des superviseurs et leurs équipes</p>
            </div>

            <div class="flex items-center gap-4">
                <Button 
                    v-if="supervisorAssignments?.some(item => Array.isArray(item.assignments) && item.assignments.some(a => a.status === 'en attente'))"
                    @click="validateAll" 
                    class="!bg-emerald-600 !border-none !rounded-2xl !px-6 !py-4 flex items-center gap-3 shadow-xl shadow-emerald-500/20 hover:!bg-emerald-700 transition-all"
                >
                    <CheckCircle class="w-5 h-5 text-white" />
                    <span class="font-black text-white text-sm uppercase tracking-wider">Tout Valider</span>
                </Button>

                <Link :href="route('planning.assignments.create')">
                    <Button class="flex-shrink-0 !bg-blue-600 !border-none !rounded-2xl !px-8 !py-4 flex items-center gap-3 shadow-xl shadow-blue-500/20 hover:!bg-blue-700 hover:-translate-y-0.5 transition-all">
                        <UserPlus class="w-5 h-5 text-white" />
                        <span class="font-black text-white text-sm uppercase tracking-wider">Nouvelle Affectation</span>
                    </Button>
                </Link>
            </div>
        </div>

        <div class="py-8 space-y-4">
            <div v-for="item in supervisorAssignments" :key="item.supervisor.id" class="bg-white/70 backdrop-blur-md rounded-[2.5rem] border border-white/50 shadow-xl shadow-slate-200/50 p-8 transition-all hover:shadow-2xl hover:shadow-blue-500/5 group">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-blue-500/20 group-hover:scale-110 transition-transform">
                            {{ item.supervisor?.name?.substring(0, 2).toUpperCase() ?? '??' }}
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center flex-wrap gap-2">
                                <h3 class="text-xl font-black text-slate-800">{{ item.supervisor?.name ?? 'Superviseur inconnu' }}</h3>
                                <div class="flex items-center gap-1.5 bg-slate-100 text-slate-600 px-3 py-1 rounded-xl border border-slate-200/50">
                                    <Users class="w-3.5 h-3.5" />
                                    <span class="text-[10px] font-black uppercase tracking-wider">{{ item.teleconseillers?.length ?? 0 }} agents</span>
                                </div>
                                <div v-if="item.supervisor.has_campaign" class="flex items-center gap-1.5 bg-blue-100/50 text-blue-700 px-3 py-1 rounded-xl border border-blue-200/50">
                                    <Megaphone class="w-3.5 h-3.5" />
                                    <span class="text-[10px] font-black uppercase tracking-wider">{{ item.supervisor.campaign_name }}</span>
                                </div>
                                <div v-if="item.assignments.length > 0" class="flex items-center gap-1.5 bg-emerald-100/50 text-emerald-700 px-3 py-1 rounded-xl border border-emerald-200/50">
                                    <CheckCircle class="w-3.5 h-3.5" />
                                    <span class="text-[10px] font-black uppercase tracking-wider">Planning assigné</span>
                                </div>
                                <div v-else class="flex items-center gap-1.5 bg-rose-100/50 text-rose-700 px-3 py-1 rounded-xl border border-rose-200/50">
                                    <AlertCircle class="w-3.5 h-3.5" />
                                    <span class="text-[10px] font-black uppercase tracking-wider">Aucun planning</span>
                                </div>
                            </div>
                            <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest mt-1">Superviseur d'équipe</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <Link :href="route('planning.assignments.create', { supervisor_id: item.supervisor.id })" class="flex-1 md:flex-none">
                            <Button class="w-full !bg-white !text-blue-600 !border-blue-100 !rounded-2xl !px-6 !py-3 !font-black !text-xs !uppercase !tracking-widest shadow-sm hover:!bg-blue-50 transition-all">
                                <UserPlus class="w-4 h-4 mr-2" />
                                Affecter planning
                            </Button>
                        </Link>
                        <Button @click="viewTeleconseillers(item)" class="flex-1 md:flex-none !bg-white !text-blue-600 !border-blue-100 !rounded-2xl !px-6 !py-3 !font-black !text-xs !uppercase !tracking-widest shadow-sm hover:!bg-blue-50 transition-all">
                            <Eye class="w-4 h-4 mr-2" />
                            Voir l'équipe
                        </Button>
                    </div>
                </div>

                <div v-if="item.assignments.length > 0" class="space-y-4">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ml-2 mb-4">Plannings du superviseur</h4>
                    <div v-for="assignment in item.assignments" :key="assignment.id" class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-white/40 border border-white/60 rounded-3xl p-6 transition-all hover:bg-white/60">
                        <div class="flex items-center gap-5 mb-4 sm:mb-0">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                                <Clock class="w-6 h-6" />
                            </div>
                            <div>
                                <p class="text-lg font-black text-slate-800">{{ assignment.model.name }}</p>
                                <p class="text-xs text-slate-500 font-bold flex items-center gap-2 mt-1">
                                    <Calendar class="w-3.5 h-3.5 text-blue-400" />
                                    Du {{ assignment.start_date }} au {{ assignment.end_date }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 w-full sm:w-auto justify-between sm:justify-end">
                            <Tag :severity="getStatusLabel(assignment.status)" :value="assignment.status.toUpperCase()" class="!text-[10px] !font-black !px-4 !py-2 !rounded-xl !tracking-widest" />
                            <div class="flex items-center gap-2">
                                <Button v-if="assignment.status === 'en attente'" @click="validateAssignment(assignment.id)" class="!bg-emerald-500 !border-none !text-white !px-4 !py-2.5 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-emerald-500/20 hover:!bg-emerald-600 transition-all">
                                    <CheckCircle class="w-4 h-4" />
                                    Valider
                                </Button>
                                <Button v-if="['validé', 'en attente'].includes(assignment.status)" @click="suspendAssignment(assignment.id)" class="!bg-amber-500 !border-none !text-white !px-4 !py-2.5 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-amber-500/20 hover:!bg-amber-600 transition-all">
                                    <PauseCircle class="w-4 h-4" />
                                    Suspendre
                                </Button>
                                <Button v-if="['validé', 'suspendu', 'en attente'].includes(assignment.status)" @click="terminateAssignment(assignment.id)" class="!bg-rose-500 !border-none !text-white !px-4 !py-2.5 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-rose-500/20 hover:!bg-rose-600 transition-all">
                                    <XCircle class="w-4 h-4" />
                                    Terminer
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-slate-400">
                    <AlertCircle class="w-8 h-8 mx-auto mb-2 text-slate-200" />
                    <p class="mb-4">Aucun planning affecté</p>
                    <Link :href="route('planning.assignments.create', { supervisor_id: item.supervisor.id })">
                        <Button class="!bg-blue-600 !text-white !border-none hover:!bg-blue-700 !px-6 !py-2 !rounded-xl shadow-lg shadow-blue-500/20 transition-all">
                            <UserPlus class="w-4 h-4 mr-2" />
                            Affecter un planning maintenant
                        </Button>
                    </Link>
                </div>
            </div>
        </div>

        <Dialog v-model="showTeleconseillersModal" header="Téléconseillers de l'équipe" :style="{ width: '60rem' }">
            <div v-if="selectedSupervisor" class="space-y-4">
                <h3 class="text-lg font-bold text-slate-800">Équipe de {{ selectedSupervisor.supervisor.name }}</h3>
                <DataTable :value="selectedSupervisor.teleconseillers" class="p-datatable-sm">
                    <Column field="employee.name" header="Téléconseiller">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-600 font-bold text-xs">
                                    {{ data.employee?.name?.substring(0, 2).toUpperCase() ?? '??' }}
                                </div>
                                <div>
                                    <span class="font-bold text-slate-700">{{ data.employee?.name ?? 'Inconnu' }}</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black ml-2">{{ data.employee?.role ?? 'TC' }}</span>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="model.name" header="Planning">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2" :class="data.has_planning ? 'text-blue-600 font-semibold' : 'text-slate-400'">
                                <Clock class="w-4 h-4" />
                                {{ data.model.name }}
                            </div>
                        </template>
                    </Column>
                    <Column header="Période">
                        <template #body="{ data }">
                            <span class="text-xs flex items-center gap-1" :class="data.has_planning ? 'text-slate-500' : 'text-slate-300'">
                                <Calendar class="w-3 h-3" />
                                Du {{ data.start_date }} au {{ data.end_date }}
                            </span>
                        </template>
                    </Column>
                    <Column field="status" header="Statut">
                        <template #body="{ data }">
                            <Tag :severity="getStatusLabel(data.status)" :value="data.status.toUpperCase()" class="!text-[10px] !font-black !px-3" />
                        </template>
                    </Column>
                    <Column header="Actions" headerStyle="width: 15rem">
                        <template #body="{ data }">
                            <div class="flex items-center gap-1.5" v-if="data.has_planning">
                                <Button v-if="data.status === 'en attente'" @click="validateAssignment(data.id)" class="!bg-emerald-500 !border-none !text-white !px-3 !py-2 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-emerald-500/10 hover:!bg-emerald-600 transition-all">
                                    <CheckCircle class="w-3.5 h-3.5" />
                                    Valider
                                </Button>
                                <Button v-if="['validé', 'en attente'].includes(data.status)" @click="suspendAssignment(data.id)" class="!bg-amber-500 !border-none !text-white !px-3 !py-2 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-amber-500/10 hover:!bg-amber-600 transition-all">
                                    <PauseCircle class="w-3.5 h-3.5" />
                                    Suspendre
                                </Button>
                                <Button v-if="['validé', 'suspendu', 'en attente'].includes(data.status)" @click="terminateAssignment(data.id)" class="!bg-rose-500 !border-none !text-white !px-3 !py-2 !text-[10px] !font-black !rounded-xl !uppercase !tracking-widest flex items-center gap-2 shadow-lg shadow-rose-500/10 hover:!bg-rose-600 transition-all">
                                    <XCircle class="w-3.5 h-3.5" />
                                    Terminer
                                </Button>
                            </div>
                            <div v-else class="text-slate-400 text-[10px] font-black uppercase tracking-widest">
                                En attente superviseur
                            </div>
                        </template>
                    </Column>
                </DataTable>
                <div v-if="selectedSupervisor.teleconseillers.length === 0" class="text-center py-12 text-slate-400">
                    <AlertCircle class="w-12 h-12 mx-auto mb-4 text-slate-200" />
                    Aucun téléconseiller dans cette équipe
                </div>
            </div>
        </Dialog>
    </AppLayout>
</template>
