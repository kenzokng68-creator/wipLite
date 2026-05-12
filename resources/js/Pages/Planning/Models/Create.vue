<script setup>
import { computed, ref } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Textarea from "primevue/textarea";
import Dialog from "primevue/dialog";
import {
    Clock,
    ArrowLeft,
    Save,
    CheckCircle2,
    Calendar, Days
} from "lucide-vue-next";

// État pour la modale de succès
const showSuccessModal = ref(false);

const form = useForm({
    name: "",
    description: "",
    monday_hours: 0,
    tuesday_hours: 0,
    wednesday_hours: 0,
    thursday_hours: 0,
    friday_hours: 0,
    saturday_hours: 0,
    sunday_hours: 0,
    total_hours: 0,
});

const weekDays = [
    { label: "Lundi", key: "monday_hours" },
    { label: "Mardi", key: "tuesday_hours" },
    { label: "Mercredi", key: "wednesday_hours" },
    { label: "Jeudi", key: "thursday_hours" },
    { label: "Vendredi", key: "friday_hours" },
    { label: "Samedi", key: "saturday_hours" },
    { label: "Dimanche", key: "sunday_hours" },
];

// Calcul automatique du total
const autoTotal = computed(() => {
    const total = weekDays.reduce((acc, day) => acc + Number(form[day.key] || 0), 0);
    form.total_hours = total;
    return total;
});

const submit = () => {
    form.post(route("planning.models.store"), {
        onSuccess: () => {
            showSuccessModal.value = true;
        },
    });
};

const goToIndex = () => {
    showSuccessModal.value = false;
    setTimeout(() => {
        router.visit(route('planning.models.index'));
    }, 100);
};
</script>

<template>
    <Head title="Créer un modèle de planning" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-4 mb-6">
                <Button
                    @click="goToIndex"
                    icon="pi pi-arrow-left"
                    class="!bg-white !text-slate-600 !border-slate-200 !rounded-xl shadow-sm hover:!bg-slate-50"
                    aria-label="Retour"
                >
                    <ArrowLeft class="w-5 h-5" />
                </Button>
                <div>
                    <h2 class="text-2xl font-black text-slate-800">Nouveau Modèle</h2>
                    <p class="text-slate-500 text-sm font-medium">Définissez une nouvelle structure horaire hebdomadaire</p>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-8">
            <form @submit.prevent="submit" class="grid grid-cols-12 gap-8">

                <!-- Section Informations Générales -->
                <div class="col-span-12 lg:col-span-7 space-y-6">
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm space-y-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Nom du modèle</label>
                            <InputText
                                v-model="form.name"
                                placeholder="Ex: Shift Matin Standard"
                                :class="{'p-invalid': form.errors.name}"
                                class="!rounded-2xl !py-4 !px-6 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500 transition-all"
                            />
                            <small class="text-red-500 font-bold" v-if="form.errors.name">{{ form.errors.name }}</small>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Description (Optionnel)</label>
                            <Textarea
                                v-model="form.description"
                                rows="3"
                                placeholder="Précisez les spécificités de ce planning..."
                                class="!rounded-2xl !py-4 !px-6 !bg-slate-50/50 !border-slate-100 focus:!bg-white focus:!border-blue-500 transition-all"
                            />
                        </div>
                    </div>

                    <!-- Grille des Heures Journalières -->
                    <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                        <h3 class="text-sm font-black text-slate-800 mb-6 flex items-center gap-2">
                            <CalendarDays class="w-5 h-5 text-blue-500" />
                            Répartition journalière
                        </h3>

                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                            <div v-for="day in weekDays" :key="day.key" class="flex flex-col gap-2">
                                <span class="text-[10px] font-black text-center text-slate-400 uppercase">{{ day.label }}</span>
                                <InputNumber
                                    v-model="form[day.key]"
                                    :min="0" :max="24" :maxFractionDigits="1"
                                    inputClass="!w-full !text-center !py-4 !rounded-xl !bg-slate-50 !border-none !font-black !text-slate-700 focus:!ring-2 !ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panneau de Résumé et Validation -->
                <div class="col-span-12 lg:col-span-5 space-y-6">
                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-8 rounded-[2rem] shadow-xl text-white relative overflow-hidden">
                        <div class="relative z-10">
                            <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Total Hebdomadaire</span>
                            <div class="flex items-baseline gap-2 mt-2">
                                <span class="text-6xl font-black tabular-nums">{{ autoTotal }}</span>
                                <span class="text-2xl font-bold text-blue-400">heures</span>
                            </div>

                            <div class="mt-8 pt-8 border-t border-white/10 space-y-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-400">Statut</span>
                                    <span class="font-bold text-green-400 flex items-center gap-2">
                                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                        Prêt à l'enregistrement
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Décoration fond -->
                        <Clock class="absolute -bottom-10 -right-10 w-40 h-40 text-white/5 rotate-12" />
                    </div>

                    <Button
                        type="submit"
                        :loading="form.processing"
                        class="!w-full !bg-blue-600 !border-none !py-6 !rounded-[1.5rem] !text-lg !font-black !text-white shadow-xl shadow-blue-100 hover:!bg-blue-700 hover:-translate-y-1 transition-all"
                    >
                        <Save class="w-5 h-5 mr-3" />
                        Enregistrer le modèle
                    </Button>
                </div>
            </form>
        </div>

        <!-- Modal de Succès -->
        <Dialog
            v-model="showSuccessModal"
            modal
            :closable="false"
            :style="{ width: '28rem' }"
            :pt="{
                root: { class: '!rounded-[2.5rem] !bg-white !p-0 !overflow-hidden' },
                content: { class: '!p-0' }
            }"
        >
            <div class="p-10 text-center">
                <div class="w-24 h-24 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <CheckCircle2 class="w-12 h-12 text-green-500" />
                </div>
                <h3 class="text-2xl font-black text-slate-800 mb-2">Modèle créé !</h3>
                <p class="text-slate-500 font-medium leading-relaxed mb-8">
                    Votre nouveau modèle de planning <span class="text-slate-800 font-bold">"{{ form.name }}"</span> a été enregistré avec succès.
                </p>
                <div class="flex flex-col gap-3">
                    <Button
                        label="Retour à la liste"
                        @click="goToIndex"
                        class="!w-full !bg-slate-900 !border-none !py-4 !rounded-2xl !font-bold"
                    />
                    <Button
                        label="Créer un autre modèle"
                        @click="() => { form.reset(); showSuccessModal = false; }"
                        text
                        class="!w-full !text-slate-400 !font-bold"
                    />
                </div>
            </div>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
/* Supprime les flèches sur les inputs numbers pour un look plus propre */
:deep(.p-inputnumber-input) {
    -moz-appearance: textfield;
}
:deep(.p-inputnumber-input::-webkit-outer-spin-button),
:deep(.p-inputnumber-input::-webkit-inner-spin-button) {
    -webkit-appearance: none;
    margin: 0;
}
</style>
