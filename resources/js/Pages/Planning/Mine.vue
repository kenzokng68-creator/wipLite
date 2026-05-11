<script setup>
/**
 * Vue "Mon Planning"
 * Permet à l'utilisateur connecté (Agent ou Superviseur) de consulter ses propres affectations de planning.
 */
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Tag from "primevue/tag";
import { Clock, Calendar, CheckCircle, AlertCircle, User } from "lucide-vue-next";

// Propriétés reçues du contrôleur
const props = defineProps({
    assignments: Array, // Liste des affectations de l'utilisateur
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
    <Head title="Mon Planning" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Mon Planning</h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">Consultez vos affectations de planning actuelles et passées</p>
            </div>
        </div>

        <div class="py-8 space-y-4">
            <div v-if="assignments.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="assignment in assignments" :key="assignment.id" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-6 hover:shadow-md transition-all">
                    <div class="flex justify-between items-start mb-4">
                        <div class="p-3 bg-blue-50 rounded-2xl text-blue-600">
                            <Clock class="w-6 h-6" />
                        </div>
                        <Tag :severity="getStatusLabel(assignment.status)" :value="assignment.status.toUpperCase()" class="!text-[10px] !font-black !px-3" />
                    </div>

                    <h3 class="text-lg font-black text-slate-800 mb-1">{{ assignment.model.name }}</h3>
                    <p class="text-xs text-slate-400 uppercase font-black mb-4">Modèle de planning</p>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3 text-slate-600">
                            <Calendar class="w-4 h-4 text-blue-500" />
                            <span class="text-sm font-bold">Du {{ assignment.start_date }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-slate-600">
                            <Calendar class="w-4 h-4 text-blue-500" />
                            <span class="text-sm font-bold">Au {{ assignment.end_date }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-slate-400">
                            <User class="w-4 h-4" />
                            <span class="text-xs font-medium italic">Assigné par {{ assignment.creator }}</span>
                        </div>
                    </div>

                    <div v-if="assignment.status === 'validé'" class="flex items-center gap-2 text-green-600 bg-green-50 p-3 rounded-xl">
                        <CheckCircle class="w-4 h-4" />
                        <span class="text-xs font-black uppercase">Planning Effectif</span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-[2rem] border border-slate-100 shadow-sm">
                <AlertCircle class="w-16 h-16 mx-auto mb-4 text-slate-100" />
                <h3 class="text-xl font-black text-slate-800 mb-2">Aucun planning trouvé</h3>
                <p class="text-slate-400 font-medium">Vous n'avez pas encore d'affectation de planning pour le moment.</p>
            </div>
        </div>
    </AppLayout>
</template>
