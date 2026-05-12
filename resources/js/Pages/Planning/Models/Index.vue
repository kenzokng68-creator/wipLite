<script setup>
import { ref, computed } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import Button from "primevue/button";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Badge from "primevue/badge";
import {
    Plus,
    Edit2,
    Calendar,
    User,
    ArrowRight,
    X,
    Clock,
    UserCog,
    Users,
    CheckCircle,
    History,
} from "lucide-vue-next";

const props = defineProps({
    planningModels: Array,
    activeAssignments: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

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

const autoTotal = computed(() => {
    const total =
        Number(form.monday_hours || 0) +
        Number(form.tuesday_hours || 0) +
        Number(form.wednesday_hours || 0) +
        Number(form.thursday_hours || 0) +
        Number(form.friday_hours || 0) +
        Number(form.saturday_hours || 0) +
        Number(form.sunday_hours || 0);
    form.total_hours = total;
    return total;
});

const openCreateModal = () => {
    isEditing.value = false;
    editingId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (model) => {
    isEditing.value = true;
    editingId.value = model.id;
    form.name = model.name;
    form.description = model.description;
    form.monday_hours = Number(model.monday_hours);
    form.tuesday_hours = Number(model.tuesday_hours);
    form.wednesday_hours = Number(model.wednesday_hours);
    form.thursday_hours = Number(model.thursday_hours);
    form.friday_hours = Number(model.friday_hours);
    form.saturday_hours = Number(model.saturday_hours);
    form.sunday_hours = Number(model.sunday_hours);
    showModal.value = true;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route("planning.models.update", editingId.value), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(route("planning.models.store"), {
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const weekDays = [
    { label: "Lun", key: "monday_hours" },
    { label: "Mar", key: "tuesday_hours" },
    { label: "Mer", key: "wednesday_hours" },
    { label: "Jeu", key: "thursday_hours" },
    { label: "Ven", key: "friday_hours" },
    { label: "Sam", key: "saturday_hours" },
    { label: "Dim", key: "sunday_hours" },
];
</script>

<template>
    <Head title="Modèles de Planning" />

    <AppLayout>
        <div class="mb-8 flex justify-between items-center bg-white/50 backdrop-blur-sm p-6 rounded-[2rem] border border-white shadow-sm">
            <div class="min-w-0">
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">
                    Paramétrage Plannings
                </h2>
                <p class="text-blue-500/70 text-xs font-bold uppercase tracking-widest mt-1">
                    Gérez vos structures horaires hebdomadaires
                </p>
            </div>

            <Button
                @click="openCreateModal"
                class="flex-shrink-0 !bg-blue-600 !border-none !rounded-2xl !px-8 !py-4 flex items-center gap-3 shadow-xl shadow-blue-500/20 hover:!bg-blue-700 hover:-translate-y-0.5 transition-all"
            >
                <Plus class="w-5 h-5 text-white" />
                <span class="font-black text-white text-sm uppercase tracking-wider">Nouveau modèle</span>
            </Button>
        </div>

        <!-- Grille des plannings -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 py-6">
            <!-- Carte pour chaque modèle -->
            <div
                v-for="model in planningModels"
                :key="model.id"
                class="bg-white border border-slate-100 rounded-[2rem] p-6 shadow-sm hover:shadow-xl hover:shadow-blue-500/5 hover:-translate-y-1 transition-all duration-300 group"
            >
                <div class="flex justify-between items-start mb-6">
                    <div class="space-y-1">
                        <h4 class="font-black text-slate-800 text-lg tracking-tight group-hover:text-blue-600 transition-colors">
                            {{ model.name }}
                        </h4>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                            <UserCog class="w-3 h-3 text-blue-400" />
                            Par {{ model.creator?.name || "Système" }}
                        </span>
                    </div>
                    <Badge
                        :value="model.total_hours + 'h'"
                        class="!bg-blue-50 !text-blue-600 !text-xs !font-black !px-3 !py-1 !rounded-xl"
                    />
                </div>

                <!-- Visualisation rapide de la semaine -->
                <div class="flex gap-1 mb-6">
                    <div
                        v-for="day in weekDays"
                        :key="day.key"
                        class="flex-1 flex flex-col items-center py-2.5 rounded-xl border text-[10px] transition-all"
                        :class="
                            Number(model[day.key]) > 0
                                ? 'bg-blue-50 border-blue-100 text-blue-600 font-black'
                                : 'bg-slate-50 border-transparent text-slate-300 opacity-40'
                        "
                    >
                        <span class="text-[8px] uppercase mb-0.5">{{ day.label[0] }}</span>
                        <span v-if="Number(model[day.key]) > 0">{{ Math.floor(model[day.key]) }}</span>
                    </div>
                </div>

                <!-- Footer de la carte -->
                <div class="flex justify-between items-center pt-5 border-t border-slate-50">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-slate-50 rounded-lg">
                            <Users class="w-3.5 h-3.5 text-slate-400" />
                        </div>
                        <span class="text-[11px] font-black text-slate-500 uppercase">
                            {{ model.assignments_count }} Assignés
                        </span>
                    </div>

                    <Button
                        @click="openEditModal(model)"
                        class="!bg-slate-50 !p-2.5 !rounded-xl hover:!bg-blue-50 transition-colors group/btn"
                    >
                        <Edit2 class="w-4 h-4 text-slate-400 group-hover/btn:text-blue-600" />
                    </Button>
                </div>
            </div>

            <!-- Carte vide pour ajout rapide -->
            <button
                @click="openCreateModal"
                class="border-2 border-dashed border-slate-200 rounded-[2rem] p-6 flex flex-col items-center justify-center gap-4 text-slate-400 hover:border-blue-300 hover:text-blue-500 hover:bg-blue-50/30 transition-all duration-300 group"
            >
                <div class="p-4 bg-slate-50 rounded-full group-hover:bg-blue-100 transition-colors">
                    <Plus class="w-8 h-8" />
                </div>
                <span class="font-black text-sm uppercase tracking-widest">Nouveau Modèle</span>
            </button>
        </div>

        <!-- Fenêtre Modale (Dialog) -->
        <Dialog
            v-model="showModal"
            modal
            header=" "
            :style="{ width: '38rem' }"
            :pt="{
                root: {
                    class: '!rounded-[2rem] !bg-white/90 !backdrop-blur-2xl !border !border-white !shadow-2xl',
                },
                content: { class: '!p-9' },
            }"
        >
            <div class="mb-10 flex justify-between items-center">
                <div class="flex items-center gap-5">
                    <div class="p-4 bg-blue-600 rounded-2xl shadow-xl shadow-blue-100">
                        <Clock class="w-6 h-6 text-white" />
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight leading-none">
                            {{ isEditing ? "Éditer le modèle" : "Nouveau modèle" }}
                        </h3>
                        <p class="text-[10px] font-bold text-blue-500/60 uppercase tracking-[0.2em] mt-2">
                            Structure horaire hebdomadaire
                        </p>
                    </div>
                </div>
                <Button
                    @click="showModal = false"
                    text
                    class="!p-2 hover:!bg-slate-100 !rounded-full transition-colors"
                >
                    <X class="w-6 h-6 text-slate-300" />
                </Button>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <div class="flex flex-col gap-3">
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Dénomination</label>
                    <InputText
                        v-model="form.name"
                        placeholder="Ex: Équipe Production Matin"
                        class="!rounded-2xl !py-5 !px-6 !bg-white !border-slate-100 !text-base !font-bold focus:!border-blue-500 !shadow-sm transition-all"
                    />
                </div>

                <div class="bg-slate-50/50 p-6 rounded-[2rem] border border-slate-100">
                    <label class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-6 block text-center">Heures par jour</label>
                    <div class="grid grid-cols-7 gap-3">
                        <div
                            v-for="day in weekDays"
                            :key="day.key"
                            class="flex flex-col gap-3"
                        >
                            <span class="text-[10px] font-black text-center text-blue-600/70 uppercase italic">{{ day.label }}</span>
                            <InputNumber
                                v-model="form[day.key]"
                                :min="0"
                                :max="24"
                                :maxFractionDigits="1"
                                inputClass="!w-full !text-center !py-4 !rounded-xl !bg-white !border-none !shadow-sm !text-sm !font-black text-slate-700 focus:!ring-2 !ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-8 rounded-[1rem] flex justify-between items-center text-white shadow-2xl shadow-blue-200">
                    <div class="flex items-center gap-5">
                        <div class="p-4 bg-white/20 rounded-2xl backdrop-blur-md border border-white/30">
                            <Clock class="w-7 h-7" />
                        </div>
                        <span class="text-[11px] font-black uppercase tracking-[0.25em] text-blue-100">Total Hebdomadaire</span>
                    </div>
                    <div class="text-5xl font-black tabular-nums tracking-tighter">
                        {{ autoTotal }}<span class="text-xl ml-2 opacity-50 font-medium">h</span>
                    </div>
                </div>

                <div class="flex gap-4 pt-2">
                    <Button
                        label="Annuler"
                        @click="showModal = false"
                        text
                        class="flex-1 !py-5 !rounded-2xl !text-sm !font-bold !text-slate-400 hover:!bg-slate-100"
                    />
                    <Button
                        type="submit"
                        :loading="form.processing"
                        class="flex-1 !bg-blue-600 !border-none !py-5 !rounded-2xl !text-sm !font-black !text-white shadow-xl shadow-blue-100 hover:!bg-blue-700 transition-all"
                    >
                        {{ isEditing ? "Appliquer les modifications" : "Créer le modèle" }}
                    </Button>
                </div>
            </form>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
:deep(.p-inputnumber-input) {
    -moz-appearance: textfield;
}
:deep(.p-inputnumber-input::-webkit-outer-spin-button),
:deep(.p-inputnumber-input::-webkit-inner-spin-button) {
    -webkit-appearance: none;
    margin: 0;
}
</style>
